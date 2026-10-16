<?php

use App\Actions\StudentPortal\AuthenticateGoogleStudent;
use App\Actions\StudentPortal\LinkStudent;
use App\Actions\StudentPortal\RequestEmailOtp;
use App\Actions\StudentPortal\VerifyEmailOtp;
use App\Mail\StudentOtpMail;
use App\Mail\StudentPortalLinkedMail;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\EmailOtpChallenge;
use App\Models\Establishment;
use App\Models\SocialAccount;
use App\Models\Student;
use App\Models\StudentLinkRequest;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\PortalEmailLock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->base = 'http://'.config('portals.bourse_domain');
    $this->studentUser = User::factory()->create(['email' => 'student@example.test'])->assignRole('student');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->a = Establishment::factory()->create();
    $this->b = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create(['is_current' => true]);
    $this->student = Student::factory()->create(['cin' => '401001001001', 'sex' => 'F', 'birth_date' => '2001-01-01', 'email' => 'student@example.test']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id]);
    $this->link = ['identifier_type' => 'cin', 'identifier' => '401001001001', 'birth_date' => '2001-01-01'];
});

function portalStudent(TestCase $test, ?User $user = null): void
{
    $test->actingAs($user ?? $test->studentUser, 'student')->withSession(['student_portal' => true]);
}
function portalOtp(TestCase $test, string $email = 'new@example.test'): array
{
    $response = $test->postJson($test->base.'/api/student/auth/otp/request', ['email' => $email])->assertOk();
    $mail = Mail::queued(StudentOtpMail::class)->last();

    return ['challenge_id' => $response->json('challenge_id'), 'code' => $mail->code];
}
function googleStudent(string $email = 'google@example.test', string $id = '123456789012345678901', bool $verified = true): GoogleUser
{
    return (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'email' => $email, 'name' => 'Untrusted Google name']);
}

it('serves each portal only on its own configured domain', function () {
    $this->get('http://'.config('portals.scol_domain').'/')->assertRedirect('/dashboard');
    $this->get('http://'.config('portals.scol_domain').'/dashboard')->assertRedirect('/login');
    $this->get($this->base.'/')->assertOk()->assertSee('bourse-root');
    $this->get($this->base.'/dashboard')->assertNotFound();
    $this->get($this->base.'/login')->assertNotFound();
    $this->get('/api/student/session')->assertNotFound();
    $this->get('http://unknown.test/')->assertNotFound();
});
it('generates front-end route URLs relative to the current portal, without the portal domain or port', function () {
    $path = sys_get_temp_dir().'/wayfinder-'.bin2hex(random_bytes(4));
    $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--path='.$path]);
    expect($result->successful())->toBeTrue();
    $generated = collect(File::allFiles($path))->map(fn ($file) => $file->getContents())->implode("\n");
    File::deleteDirectory($path);
    expect($generated)->toContain("url: '/login'")->toContain("url: '/students'")->toContain('export const home =')->toContain('export const dashboard =')
        ->not->toContain('//'.config('portals.scol_domain'));
    // Runtime host routing is unchanged.
    $this->get('http://'.config('portals.scol_domain').'/login')->assertOk();
    $this->get($this->base.'/login')->assertNotFound();
});
it('rejects students on all internal auth and settings routes', function (string $url) {
    $this->actingAs($this->studentUser, 'web')->get($url)->assertForbidden();
})->with(['/dashboard', '/settings/profile', '/settings/appearance', '/students']);
it('does not authenticate students through Fortify passwords or password recovery', function () {
    $this->post('/login', ['email' => $this->studentUser->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest('web');
    $this->post('/forgot-password', ['email' => $this->studentUser->email])->assertSessionHasErrors('email');
});
it('rejects administrative and mixed-role accounts on student endpoints', function (string $role) {
    $this->studentUser->assignRole($role);
    portalStudent($this);
    $this->getJson($this->base.'/api/student/me')->assertForbidden();
    $this->getJson($this->base.'/api/student/session')->assertForbidden();
})->with(['superadmin', 'developer', 'central_scholarship_office', 'establishment']);
it('requires authentication and the student session marker', function () {
    $this->getJson($this->base.'/api/student/me')->assertUnauthorized();
    $this->actingAs($this->studentUser, 'student')->getJson($this->base.'/api/student/me')->assertForbidden();
});
it('uses a distinct host-only HttpOnly session cookie on each portal', function () {
    config(['session.domain' => '.umg.test']);
    foreach ([['scol.umg.test', 'umg-scol-session'], ['bourse.umg.test', 'umg-bourse-session']] as [$domain,$name]) {
        $response = $this->get('http://'.$domain.'/');
        $cookie = collect($response->headers->getCookies())->first(fn ($item) => $item->getName() === $name);
        expect($cookie)->not->toBeNull()->and($cookie->getDomain())->toBeNull()->and($cookie->isHttpOnly())->toBeTrue()->and($cookie->getSameSite())->toBe('lax');
    }
});
it('does not use an internal web session to authenticate a student API', function () {
    $this->actingAs($this->admin, 'web')->getJson($this->base.'/api/student/me')->assertUnauthorized();
});
it('rejects bearer authentication without issuing or looking up API tokens', function () {
    $this->withHeader('Authorization', 'Bearer fake-secret')->getJson($this->base.'/api/student/me')->assertUnauthorized();
    expect(DB::getSchemaBuilder()->hasTable('personal_access_tokens'))->toBeFalse();
});
it('creates an expiring hashed challenge bound to the requesting browser', function () {
    $data = portalOtp($this);
    $challenge = EmailOtpChallenge::sole();
    expect($challenge->id)->toBe($data['challenge_id'])->and($challenge->code_hash)->not->toBe($data['code'])->and(Hash::check(PortalEmailLock::key('otp:'.$data['code']), $challenge->code_hash))->toBeTrue()->and($challenge->expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(10);
    expect($challenge->toJson())->not->toContain($data['code'], $challenge->email);
});
it('creates a student account after valid OTP and rotates the session', function () {
    $data = portalOtp($this);
    $old = app('session')->getId();
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertOk()->assertJsonPath('authenticated', true)->assertJsonPath('linked', false);
    $user = User::query()->where('email', 'new@example.test')->sole();
    expect($user->getRoleNames()->all())->toBe(['student'])->and($user->hasVerifiedEmail())->toBeTrue()->and($user->student_id)->toBeNull()->and(app('session')->getId())->not->toBe($old)->and(Student::count())->toBe(1);
});
it('rejects incorrect expired consumed or wrong-session OTP', function (string $case) {
    $data = portalOtp($this);
    if ($case === 'incorrect') {
        $data['code'] = $data['code'] === '111111' ? '222222' : '111111';
    }
    if ($case === 'expired') {
        EmailOtpChallenge::query()->update(['expires_at' => now()->subSecond()]);
    }
    if ($case === 'consumed') {
        EmailOtpChallenge::query()->update(['consumed_at' => now()]);
    }
    if ($case === 'session') {
        $this->withSession(['otp_binding' => 'other-browser']);
    }
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertUnprocessable();
    expect(User::where('email', 'new@example.test')->count())->toBe(0);
})->with(['incorrect', 'expired', 'consumed', 'session']);
it('consumes an OTP only once', function () {
    $data = portalOtp($this);
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertOk();
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertUnprocessable();
});
it('enforces the attempt ceiling in the transactional verifier', function () {
    $binding = 'binding';
    $id = app(RequestEmailOtp::class)->handle('new@example.test', $binding);
    $correct = Mail::queued(StudentOtpMail::class)->last()->code;
    $wrong = $correct === '111111' ? '222222' : '111111';
    for ($i = 0; $i < 5; $i++) {
        try {
            app(VerifyEmailOtp::class)->handle($id, $wrong, $binding);
        } catch (ValidationException) {
        }
    }
    expect(EmailOtpChallenge::sole()->attempts)->toBe(5);
    expect(fn () => app(VerifyEmailOtp::class)->handle($id, $correct, $binding))->toThrow(ValidationException::class);
});
it('rate limits requests and verification', function () {
    $last = null;
    for ($i = 0; $i < 3; $i++) {
        $last = portalOtp($this);
    }
    $this->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertTooManyRequests();
    $wrong = [...$last, 'code' => '000000'];
    for ($i = 0; $i < 5; $i++) {
        $this->postJson($this->base.'/api/student/auth/otp/verify', $wrong)->assertUnprocessable();
    }
    $this->postJson($this->base.'/api/student/auth/otp/verify', $wrong)->assertTooManyRequests();
});
it('caps OTP requests per address over the hour so challenges cannot be renewed indefinitely', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->travel(61)->seconds();
        portalOtp($this);
    }
    $this->travel(61)->seconds();
    $this->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertTooManyRequests();
});
it('keeps OTP login available and Google disabled without a complete Google configuration', function (array $google) {
    config(['services.google' => $google]);
    $this->getJson($this->base.'/api/student/session')->assertOk()->assertJsonPath('google_enabled', false);
    $this->get($this->base.'/api/student/auth/google/redirect')->assertStatus(503);
    $this->get($this->base.'/api/student/auth/google/callback?state=x&code=y')->assertRedirect('/?auth_error=google');
    portalOtp($this);
})->with([
    'absent' => [['client_id' => null, 'client_secret' => null, 'redirect' => null]],
    'no redirect' => [['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => null]],
]);
it('reuses an existing student email but never promotes an administrative account', function (string $role) {
    $target = $role === 'student' ? $this->studentUser : $this->admin;
    $data = portalOtp($this, $target->email);
    $response = $this->postJson($this->base.'/api/student/auth/otp/verify', $data);
    if ($role === 'student') {
        $response->assertOk();
    } else {
        $response->assertUnprocessable();
    }
    expect(User::where('email', $target->email)->count())->toBe(1)->and($target->fresh()->getRoleNames()->all())->toBe([$role]);
})->with(['student', 'superadmin']);
it('invalidates previous challenges without creating duplicate accounts', function () {
    $one = portalOtp($this);
    $two = portalOtp($this);
    expect(EmailOtpChallenge::count())->toBe(1);
    $this->postJson($this->base.'/api/student/auth/otp/verify', $one)->assertUnprocessable();
    $this->postJson($this->base.'/api/student/auth/otp/verify', $two)->assertOk();
    expect(User::where('email', 'new@example.test')->count())->toBe(1);
});
it('never sends an OTP through a logging mail transport', function () {
    config(['mail.default' => 'log']);
    $this->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertStatus(503);
    expect(EmailOtpChallenge::count())->toBe(0);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});
it('queues the code encrypted, so that the page answers without waiting for the mail server', function () {
    $this->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertOk();
    Mail::assertQueued(StudentOtpMail::class, fn (StudentOtpMail $mail) => $mail->hasTo('new@example.test') && preg_match('/^\d{6}$/', $mail->code) === 1);
    expect(new StudentOtpMail('123456'))->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and((new StudentOtpMail('123456'))->render())->toContain('123456')->toContain('10 minutes');
});
it('cleans expired and consumed challenges', function () {
    portalOtp($this);
    EmailOtpChallenge::query()->update(['expires_at' => now()->subSecond()]);
    $this->artisan('students:purge-otp')->assertSuccessful();
    expect(EmailOtpChallenge::count())->toBe(0);
});
it('authenticates a mocked verified Google profile without storing tokens', function () {
    $profile = googleStudent();
    $profile->token = 'ACCESS-SECRET';
    $profile->refreshToken = 'REFRESH-SECRET';
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->once()->andReturn($profile);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    $this->get($this->base.'/api/student/auth/google/callback')->assertRedirect('/');
    $user = User::where('email', 'google@example.test')->sole();
    expect($user->getRoleNames()->all())->toBe(['student'])->and(SocialAccount::count())->toBe(1)->and(SocialAccount::sole()->toJson())->not->toContain('ACCESS-SECRET', 'REFRESH-SECRET');
});
it('rejects unverified or malformed Google identities', function (bool $verified, string $id) {
    expect(fn () => app(AuthenticateGoogleStudent::class)->handle(googleStudent(verified: $verified, id: $id), null))->toThrow(ValidationException::class);
    expect(SocialAccount::count())->toBe(0);
})->with([[false, '123'], [true, ''], [true, str_repeat('1', 192)]]);
it('does not attach Google to an existing account merely by email equality', function () {
    expect(fn () => app(AuthenticateGoogleStudent::class)->handle(googleStudent('student@example.test'), null))->toThrow(ValidationException::class);
    expect(fn () => app(AuthenticateGoogleStudent::class)->handle(googleStudent($this->admin->email), null))->toThrow(ValidationException::class);
    expect(SocialAccount::count())->toBe(0);
    $user = app(AuthenticateGoogleStudent::class)->handle(googleStudent('student@example.test'), $this->studentUser);
    expect($user->id)->toBe($this->studentUser->id)->and(SocialAccount::count())->toBe(1);
    expect(app(AuthenticateGoogleStudent::class)->handle(googleStudent('student@example.test'), null)->id)->toBe($user->id);
});
it('uses stateful Socialite redirects and rejects invalid state', function () {
    config(['services.google' => ['client_id' => 'test-client', 'client_secret' => 'test-secret', 'redirect' => $this->base.'/api/student/auth/google/callback']]);
    $response = $this->get($this->base.'/api/student/auth/google/redirect')->assertRedirect();
    expect($response->headers->get('Location'))->toContain('state=');
    $this->get($this->base.'/api/student/auth/google/callback?state=wrong&code=wrong')->assertRedirect('/?auth_error=google');
    expect(SocialAccount::count())->toBe(0);
});
it('automatically links only an exact official identity and verified email', function (string $cin) {
    portalStudent($this);
    $this->postJson($this->base.'/api/student/link', [...$this->link, 'identifier' => $cin])->assertOk()->assertJsonPath('linked', true);
    expect($this->studentUser->fresh()->student_id)->toBe($this->student->id)->and(StudentLinkRequest::count())->toBe(0)->and($this->studentUser->fresh()->student->id)->toBe($this->student->id)->and($this->student->user->id)->toBe($this->studentUser->id);
})->with(['401001001001', ' 401 001 001 001 ', '401.001.001.001']);
it('returns the same generic response for missing ambiguous inactive taken and email-conflicting identities', function (string $case) {
    portalStudent($this);
    if ($case === 'missing') {
        $this->link['identifier'] = '401001001002';
    }
    if ($case === 'ambiguous') {
        Student::factory()->create(['cin' => '401001001001', 'birth_date' => '2001-01-01']);
    }
    if ($case === 'birth') {
        $this->link['birth_date'] = '2001-01-02';
    }
    if ($case === 'inactive') {
        $this->student->update(['is_active' => false]);
    }
    if ($case === 'taken') {
        User::factory()->create()->assignRole('student')->forceFill(['student_id' => $this->student->id])->save();
    }
    $response = $this->postJson($this->base.'/api/student/link', $this->link)->assertOk()->assertExactJson(['linked' => false, 'message' => 'Ces informations ne correspondent à aucune fiche disponible. Vérifiez-les, essayez un autre renseignement ou contactez votre établissement.']);
    expect($response->getContent())->not->toContain($this->student->last_name, 'other@example.test', '401001001001');
    $this->getJson($this->base.'/api/student/link/status')->assertExactJson(['linked' => false, 'status' => 'verification_required']);
})->with(['missing', 'birth', 'ambiguous', 'inactive', 'taken']);
it('links at once without staff review when the official email differs, and alerts the official address', function (?string $official) {
    $this->student->update(['email' => $official]);
    portalStudent($this);

    $this->postJson($this->base.'/api/student/link', $this->link)->assertOk()->assertJsonPath('linked', true);
    expect($this->studentUser->fresh()->student_id)->toBe($this->student->id)->and(StudentLinkRequest::count())->toBe(0);
    if ($official === null) {
        Mail::assertNotQueued(StudentPortalLinkedMail::class);
    } else {
        Mail::assertQueued(StudentPortalLinkedMail::class, fn ($mail) => $mail->hasTo($official));
    }
})->with(['other official email' => ['officiel@example.test'], 'no official email' => [null]]);
it('lets the staff detach a wrong portal account from the record', function () {
    app(LinkStudent::class)->handle($this->studentUser, 'cin', '401001001001', '2001-01-01');
    $central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->actingAs($central, 'web')->delete('/students/'.$this->student->uuid.'/portal')->assertRedirect();
    expect($this->studentUser->fresh()->student_id)->toBeNull()
        ->and(Activity::query()->where('event', 'portal_detached')->sole()->causer_id)->toBe($central->id);
    $this->actingAs($this->studentUser, 'web')->delete('/students/'.$this->student->uuid.'/portal')->assertForbidden();
});
it('allows only one account to link an identity and enforces it in SQL too', function () {
    app(LinkStudent::class)->handle($this->studentUser, 'cin', '401001001001', '2001-01-01');
    $other = User::factory()->create(['email' => 'other@example.test'])->assignRole('student');
    expect(app(LinkStudent::class)->handle($other, 'cin', '401001001001', '2001-01-01'))->toBeFalse();
    expect(fn () => DB::table('users')->where('id', $other->id)->update(['student_id' => $this->student->id]))->toThrow(QueryException::class);
});
it('requires linkage and returns only the linked profile with controlled annual data', function () {
    portalStudent($this);
    $this->getJson($this->base.'/api/student/me')->assertForbidden();
    app(LinkStudent::class)->handle($this->studentUser, 'cin', '401001001001', '2001-01-01');
    $this->studentUser->refresh();
    $other = Student::factory()->create(['last_name' => 'OTHER SECRET']);
    StudentRecord::factory()->create(['student_id' => $other->id]);
    $response = $this->getJson($this->base.'/api/student/me?student_id='.$other->id)->assertOk()->assertJsonPath('student.last_name', $this->student->last_name)->assertJsonCount(1, 'student.records')->assertJsonPath('student.sex', 'F')->assertJsonPath('student.nationality', 'Malagasy')->assertJsonPath('student.records.0.repeat_code', 'N (Nouveau)')->assertJsonMissingPath('student.cin')->assertJsonMissingPath('student.cin_issued_place')->assertJsonMissingPath('student.id')->assertJsonMissingPath('student.records.0.declared_by');
    expect($response->getContent())->not->toContain('OTHER SECRET');
});
it('audits account creation authentication linkage and logout without sensitive values', function () {
    $data = portalOtp($this, 'student@example.test');
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertOk();
    $this->postJson($this->base.'/api/student/link', $this->link)->assertOk();
    $this->postJson($this->base.'/api/student/logout', [])->assertOk();
    $json = Activity::all()->toJson();
    foreach ([$data['code'], 'student@example.test', '2001-01-01', '401001001001'] as $secret) {
        expect($json)->not->toContain($secret);
    }
    expect(Activity::where('log_name', 'student_portal')->pluck('event')->all())->toBe(['otp_login', 'automatic_link']);
    // The sanctum RequestGuard caches its user on the shared test application; a real request starts fresh.
    $this->app['auth']->forgetGuards();
    $this->getJson($this->base.'/api/student/me')->assertUnauthorized();
});
it('blocks link brute force and unverified accounts', function () {
    portalStudent($this);
    for ($i = 0; $i < 3; $i++) {
        $this->postJson($this->base.'/api/student/link', [...$this->link, 'identifier' => 'missing'])->assertOk();
    }
    $this->postJson($this->base.'/api/student/link', $this->link)->assertTooManyRequests();
    $this->studentUser->forceFill(['email_verified_at' => null])->save();
    $this->getJson($this->base.'/api/student/me')->assertForbidden();
});
it('rejects a duplicate canonical email regardless of case', function () {
    expect(fn () => User::factory()->create(['email' => 'STUDENT@EXAMPLE.TEST']))->toThrow(QueryException::class);
});
it('enforces CSRF for state changing student endpoints', function () {
    $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
    $this->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertStatus(419);
    $session = $this->getJson($this->base.'/api/student/session')->assertOk()->json('csrf_token');
    $this->withHeader('X-CSRF-TOKEN', $session)->postJson($this->base.'/api/student/auth/otp/request', ['email' => 'new@example.test'])->assertOk();
});
it('rejects cross-portal origins even with a valid session', function () {
    portalStudent($this);
    $this->withHeader('Origin', 'http://scol.umg.test')->postJson($this->base.'/api/student/logout', [])->assertStatus(419);
});

it('links a student without CIN by phone, email or last name, with the birth date', function (string $type, string $identifier) {
    $this->student->update(['cin' => null, 'phone' => '0341112233', 'email' => 'student@example.test', 'last_name' => 'RAZAFY']);
    portalStudent($this);

    $this->student->update(['mother_name' => 'RASOA Marie']);
    $this->postJson($this->base.'/api/student/link', ['identifier_type' => $type, 'identifier' => $identifier, 'birth_date' => '2001-01-01', 'mother_name' => 'rasoa  marie'])->assertOk()->assertJsonPath('linked', true);
    expect($this->studentUser->fresh()->student_id)->toBe($this->student->id);
})->with([
    'phone written with spaces and +261' => ['phone', '+261 34 11 122 33'],
    'email in capitals' => ['email', 'STUDENT@example.test'],
    'last name without accents or case' => ['last_name', 'razafy'],
]);

it('asks the mother\'s name with the last name, and refuses a wrong one', function () {
    $this->student->update(['cin' => null, 'last_name' => 'RAZAFY', 'mother_name' => 'RASOA Marie']);
    portalStudent($this);

    $this->postJson($this->base.'/api/student/link', ['identifier_type' => 'last_name', 'identifier' => 'Razafy', 'birth_date' => '2001-01-01'])->assertUnprocessable()->assertJsonValidationErrors('mother_name');
    $this->postJson($this->base.'/api/student/link', ['identifier_type' => 'last_name', 'identifier' => 'Razafy', 'birth_date' => '2001-01-01', 'mother_name' => 'RABE Lalao'])->assertOk()->assertJsonPath('linked', false);
    expect($this->studentUser->fresh()->student_id)->toBeNull()->and(StudentLinkRequest::query()->exists())->toBeFalse();
});
it('never links by name when two records share the name, the birth date and the mother\'s name', function () {
    $this->student->update(['last_name' => 'RAZAFY', 'mother_name' => 'RASOA Marie']);
    Student::factory()->create(['last_name' => 'Razafy', 'birth_date' => '2001-01-01', 'mother_name' => 'Rasoa Marie']);
    portalStudent($this);

    $this->postJson($this->base.'/api/student/link', ['identifier_type' => 'last_name', 'identifier' => 'RAZAFY', 'birth_date' => '2001-01-01', 'mother_name' => 'RASOA Marie'])->assertOk()->assertJsonPath('linked', false);
    expect(StudentLinkRequest::query()->exists())->toBeFalse()->and($this->studentUser->fresh()->student_id)->toBeNull();
    $this->postJson($this->base.'/api/student/link', ['identifier_type' => 'matricule', 'identifier' => 'X', 'birth_date' => '2001-01-01'])->assertUnprocessable();
});
