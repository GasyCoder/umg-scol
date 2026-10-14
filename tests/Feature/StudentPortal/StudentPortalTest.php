<?php

use App\Actions\StudentPortal\AuthenticateGoogleStudent;
use App\Actions\StudentPortal\LinkStudent;
use App\Actions\StudentPortal\RequestEmailOtp;
use App\Actions\StudentPortal\ReviewStudentLink;
use App\Actions\StudentPortal\VerifyEmailOtp;
use App\Enums\StudentLinkStatus;
use App\Mail\StudentOtpMail;
use App\Models\AcademicYear;
use App\Models\EmailOtpChallenge;
use App\Models\Establishment;
use App\Models\SocialAccount;
use App\Models\Student;
use App\Models\StudentLinkRequest;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\PortalEmailLock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Spatie\Activitylog\Models\Activity;
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
    $this->student = Student::factory()->create(['student_number' => 'MAT001', 'bac_registration_number' => 'BAC001', 'birth_date' => '2001-01-01', 'email' => 'student@example.test']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id]);
    $this->link = ['identifier_type' => 'student_number', 'identifier' => 'MAT001', 'birth_date' => '2001-01-01'];
});

function portalStudent(TestCase $test, ?User $user = null): void
{
    $test->actingAs($user ?? $test->studentUser, 'student')->withSession(['student_portal' => true]);
}
function portalOtp(TestCase $test, string $email = 'new@example.test'): array
{
    $response = $test->postJson($test->base.'/api/student/auth/otp/request', ['email' => $email])->assertOk();
    $mail = Mail::sent(StudentOtpMail::class)->last();

    return ['challenge_id' => $response->json('challenge_id'), 'code' => $mail->code];
}
function googleStudent(string $email = 'google@example.test', string $id = '123456789012345678901', bool $verified = true): GoogleUser
{
    return (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'email' => $email, 'name' => 'Untrusted Google name']);
}
function pendingPortalLink(TestCase $test): StudentLinkRequest
{
    $test->student->update(['email' => null]);
    app(LinkStudent::class)->handle($test->studentUser, 'student_number', 'MAT001', '2001-01-01');

    return StudentLinkRequest::sole();
}

it('serves each portal only on its own configured domain', function () {
    $this->get('http://'.config('portals.scol_domain').'/')->assertOk();
    $this->get($this->base.'/')->assertOk()->assertSee('bourse-root');
    $this->get($this->base.'/dashboard')->assertNotFound();
    $this->get($this->base.'/login')->assertNotFound();
    $this->get('/api/student/session')->assertNotFound();
    $this->get('http://unknown.test/')->assertNotFound();
});
it('rejects students on all internal auth and settings routes', function (string $url) {
    $this->actingAs($this->studentUser, 'web')->get($url)->assertForbidden();
})->with(['/dashboard', '/settings/profile', '/settings/appearance', '/students', '/student-links']);
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
    $correct = Mail::sent(StudentOtpMail::class)->last()->code;
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
it('automatically links only an exact official identity and verified email', function (string $field, string $value) {
    portalStudent($this);
    $this->postJson($this->base.'/api/student/link', [...$this->link, 'identifier_type' => $field, 'identifier' => $value])->assertOk()->assertJsonPath('linked', true);
    expect($this->studentUser->fresh()->student_id)->toBe($this->student->id)->and(StudentLinkRequest::count())->toBe(0)->and($this->studentUser->fresh()->student->id)->toBe($this->student->id)->and($this->student->user->id)->toBe($this->studentUser->id);
})->with([['student_number', 'MAT001'], ['bac_registration_number', 'BAC001']]);
it('returns the same generic response for missing ambiguous inactive taken and email-conflicting identities', function (string $case) {
    portalStudent($this);
    if ($case === 'missing') {
        $this->link['identifier'] = 'UNKNOWN';
    }
    if ($case === 'ambiguous') {
        Student::factory()->create(['student_number' => 'MAT001', 'birth_date' => '2001-01-01']);
    }
    if ($case === 'inactive') {
        $this->student->update(['is_active' => false]);
    }
    if ($case === 'taken') {
        User::factory()->create()->assignRole('student')->forceFill(['student_id' => $this->student->id])->save();
    }
    if ($case === 'email') {
        $this->student->update(['email' => 'other@example.test']);
    }
    if ($case === 'no-email') {
        $this->student->update(['email' => null]);
    }
    $response = $this->postJson($this->base.'/api/student/link', $this->link)->assertOk()->assertExactJson(['linked' => false, 'message' => 'Les informations fournies ne permettent pas un rattachement automatique. Une vérification peut être nécessaire.']);
    expect($response->getContent())->not->toContain($this->student->last_name, 'other@example.test', 'MAT001');
    $this->getJson($this->base.'/api/student/link/status')->assertExactJson(['linked' => false, 'status' => 'verification_required']);
})->with(['missing', 'ambiguous', 'inactive', 'taken', 'email', 'no-email']);
it('creates a minimal pending manual request without persisting submitted PII', function () {
    $request = pendingPortalLink($this);
    expect($request->status)->toBe(StudentLinkStatus::Pending)->and($request->student_id)->toBe($this->student->id)->and($request->toJson())->not->toContain('MAT001', '2001-01-01');
    app(LinkStudent::class)->handle($this->studentUser, 'student_number', 'MAT001', '2001-01-01');
    expect(StudentLinkRequest::count())->toBe(1);
});
it('scopes establishment review to the active declaration in the current year', function () {
    $request = pendingPortalLink($this);
    $staff = User::factory()->create()->assignRole('establishment');
    $staff->forceFill(['establishment_id' => $this->b->id])->save();
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'establishment_id' => $this->b->id, 'status' => 'inactive']);
    $this->actingAs($staff, 'web')->get('/student-links')->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
    $this->post('/student-links/'.$request->id.'/review', ['decision' => 'approve'])->assertForbidden();
    $staff->forceFill(['establishment_id' => $this->a->id])->save();
    $this->get('/student-links')->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
});
it('allows central global review but never a student', function () {
    $request = pendingPortalLink($this);
    $central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->actingAs($central, 'web')->get('/student-links')->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    $this->actingAs($this->studentUser, 'web')->post('/student-links/'.$request->id.'/review', ['decision' => 'approve'])->assertForbidden();
});
it('approves atomically and makes a repeated approval a no-op', function () {
    $request = pendingPortalLink($this);
    app(ReviewStudentLink::class)->handle($this->admin, $request, true, null);
    $count = Activity::count();
    app(ReviewStudentLink::class)->handle($this->admin, $request, true, null);
    expect($request->fresh()->status)->toBe(StudentLinkStatus::Approved)->and($request->fresh()->reviewed_by)->toBe($this->admin->id)->and($this->studentUser->fresh()->student_id)->toBe($this->student->id)->and(Activity::count())->toBe($count);
});
it('requires a rejection reason and leaves the account unlinked', function () {
    $request = pendingPortalLink($this);
    $this->actingAs($this->admin, 'web')->post('/student-links/'.$request->id.'/review', ['decision' => 'reject'])->assertSessionHasErrors('rejection_reason');
    $this->post('/student-links/'.$request->id.'/review', ['decision' => 'reject', 'rejection_reason' => 'Justificatifs insuffisants.'])->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(StudentLinkStatus::Rejected)->and($this->studentUser->fresh()->student_id)->toBeNull();
});
it('refuses approval if the account role identity activity or association changed', function (string $case) {
    $request = pendingPortalLink($this);
    if ($case === 'role') {
        $this->studentUser->assignRole('superadmin');
    }
    if ($case === 'inactive') {
        $this->student->update(['is_active' => false]);
    }
    if ($case === 'taken') {
        User::factory()->create()->assignRole('student')->forceFill(['student_id' => $this->student->id])->save();
    }
    expect(fn () => app(ReviewStudentLink::class)->handle($this->admin, $request, true, null))->toThrow(ValidationException::class);
    expect($request->fresh()->status)->toBe(StudentLinkStatus::Pending);
})->with(['role', 'inactive', 'taken']);
it('allows only one account to link an identity and enforces it in SQL too', function () {
    app(LinkStudent::class)->handle($this->studentUser, 'student_number', 'MAT001', '2001-01-01');
    $other = User::factory()->create(['email' => 'other@example.test'])->assignRole('student');
    expect(app(LinkStudent::class)->handle($other, 'student_number', 'MAT001', '2001-01-01'))->toBeFalse();
    expect(fn () => DB::table('users')->where('id', $other->id)->update(['student_id' => $this->student->id]))->toThrow(QueryException::class);
});
it('protects against a manual approval after a concurrent automatic link', function () {
    $request = pendingPortalLink($this);
    $this->student->update(['email' => 'student@example.test']);
    app(LinkStudent::class)->handle($this->studentUser, 'student_number', 'MAT001', '2001-01-01');
    expect($request->fresh()->status)->toBe(StudentLinkStatus::Cancelled);
    expect(fn () => app(ReviewStudentLink::class)->handle($this->admin, $request, true, null))->toThrow(ValidationException::class);
});
it('requires linkage and returns only the linked profile with controlled annual data', function () {
    portalStudent($this);
    $this->getJson($this->base.'/api/student/me')->assertForbidden();
    app(LinkStudent::class)->handle($this->studentUser, 'student_number', 'MAT001', '2001-01-01');
    $this->studentUser->refresh();
    $other = Student::factory()->create(['last_name' => 'OTHER SECRET']);
    StudentRecord::factory()->create(['student_id' => $other->id]);
    $response = $this->getJson($this->base.'/api/student/me?student_id='.$other->id)->assertOk()->assertJsonPath('student.last_name', $this->student->last_name)->assertJsonCount(1, 'student.records')->assertJsonMissingPath('student.cin')->assertJsonMissingPath('student.id')->assertJsonMissingPath('student.records.0.declared_by');
    expect($response->getContent())->not->toContain('OTHER SECRET');
});
it('audits account creation authentication linkage and logout without sensitive values', function () {
    $this->student->update(['cin' => '101234567890']);
    $data = portalOtp($this, 'student@example.test');
    $this->postJson($this->base.'/api/student/auth/otp/verify', $data)->assertOk();
    $this->postJson($this->base.'/api/student/link', $this->link)->assertOk();
    $this->postJson($this->base.'/api/student/logout', [])->assertOk();
    $json = Activity::all()->toJson();
    foreach ([$data['code'], 'student@example.test', '2001-01-01', 'MAT001', $this->student->cin] as $secret) {
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
