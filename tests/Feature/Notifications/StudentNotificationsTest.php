<?php

use App\Actions\Corrections\RequestStudentCorrection;
use App\Actions\StudentPortal\ConfirmStudentInformation;
use App\Enums\StudentCorrectionField;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\StudentInformationConfirmation;
use App\Models\StudentRecord;
use App\Models\User;
use App\Notifications\StudentActivity;
use App\Support\NotificationInbox;
use App\Support\StaffNotifications;
use App\Support\StudentConfirmationContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->a = Establishment::factory()->create();
    $this->b = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $this->student = Student::factory()->create(['last_name' => 'RABE', 'first_names' => 'Aina', 'phone' => '0341234567']);
    $this->record = StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id]);
    $this->studentUser = User::factory()->create()->assignRole('student');
    $this->studentUser->forceFill(['student_id' => $this->student->id])->save();
    $this->staffA = User::factory()->create()->assignRole('establishment');
    $this->staffA->forceFill(['establishment_id' => $this->a->id])->save();
    $this->staffB = User::factory()->create()->assignRole('establishment');
    $this->staffB->forceFill(['establishment_id' => $this->b->id])->save();
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->developer = User::factory()->create()->assignRole('developer');
    $this->requestCorrection = fn () => app(RequestStudentCorrection::class)->handle($this->studentUser, StudentCorrectionField::Phone, '0381234567', 'Message privé non notifié');
});

it('notifies the current establishment central office and superadmin without personal values', function () {
    $correction = ($this->requestCorrection)();
    foreach ([$this->staffA, $this->central, $this->admin] as $recipient) {
        $notification = $recipient->notifications()->sole();
        expect($notification->data['kind'])->toBe(StudentActivity::CORRECTION_REQUESTED)
            ->and($notification->data['message'])->toContain('RABE Aina')->and($notification->data['subject_uuid'])->toBe($correction->uuid)
            ->and(json_encode($notification->data))->not->toContain('0341234567', '0381234567', 'Message privé');
    }
    foreach ([$this->staffB, $this->developer, $this->studentUser] as $excluded) {
        expect($excluded->notifications()->count())->toBe(0);
    }
    expect(DatabaseNotification::count())->toBe(3);
    expect(Activity::query()->where('event', 'correction_requested')->count())->toBe(1);
});

it('does not notify suspended unverified inactive establishment or mixed developer accounts', function () {
    $this->staffA->forceFill(['is_active' => false])->save();
    $this->central->forceFill(['email_verified_at' => null])->save();
    $this->admin->assignRole('developer');
    $otherStaff = User::factory()->create()->assignRole('establishment');
    $otherStaff->forceFill(['establishment_id' => $this->a->id])->save();
    $this->a->update(['is_active' => false]);
    ($this->requestCorrection)();
    expect(DatabaseNotification::count())->toBe(0);
});

it('honours disabled types and individual permission denials at delivery', function () {
    $this->staffA->forceFill(['notification_preferences' => ['off' => [StudentActivity::CORRECTION_REQUESTED]]])->save();
    $this->central->deniedPermissions()->attach(Permission::findByName('corrections.view'));
    ($this->requestCorrection)();
    expect($this->staffA->notifications()->count())->toBe(0)->and($this->central->notifications()->count())->toBe(0)
        ->and($this->admin->notifications()->count())->toBe(1);
});

it('notifies an information confirmation once and leaves the official identity unchanged', function () {
    $before = $this->student->fresh()->getAttributes();
    $version = StudentConfirmationContext::load($this->student->fresh())->forStudent()['version'];
    app(ConfirmStudentInformation::class)->handle($this->studentUser, $version);
    app(ConfirmStudentInformation::class)->handle($this->studentUser, $version);
    expect(StudentInformationConfirmation::count())->toBe(1)->and(DatabaseNotification::count())->toBe(3)
        ->and($this->student->fresh()->getAttributes())->toBe($before);
    expect($this->central->notifications()->sole()->data['kind'])->toBe(StudentActivity::INFORMATION_CONFIRMED);
});

it('does not duplicate notifications for a repeated pending correction', function () {
    ($this->requestCorrection)();
    expect(fn () => ($this->requestCorrection)())->toThrow(ValidationException::class);
    expect(DatabaseNotification::count())->toBe(3);
});

it('rolls back the event audit and notifications together', function () {
    DB::beginTransaction();
    ($this->requestCorrection)();
    expect(DatabaseNotification::count())->toBe(3);
    DB::rollBack();
    expect(DatabaseNotification::count())->toBe(0)->and(StudentCorrectionRequest::count())->toBe(0)
        ->and(Activity::query()->where('event', 'correction_requested')->count())->toBe(0);
});

it('does not persist an event when a database notification cannot be saved', function () {
    DatabaseNotification::creating(function () {
        throw new RuntimeException('Simulated notification storage failure');
    });
    try {
        expect(fn () => ($this->requestCorrection)())->toThrow(RuntimeException::class, 'Simulated notification storage failure');
    } finally {
        DatabaseNotification::flushEventListeners();
    }
    expect(StudentCorrectionRequest::count())->toBe(0)->and(DatabaseNotification::count())->toBe(0)
        ->and(Activity::query()->where('event', 'correction_requested')->count())->toBe(0);
});

it('keeps the shared bell feed separate from the paginated notification page', function () {
    ($this->requestCorrection)();
    $this->actingAs($this->central)->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('notifications/index')->where('entries.total', 1)->where('notifications.unread', 1)
        ->has('notifications.recent', 1)->missing('entries.data.0.data.student_uuid')->missing('entries.data.0.notifiable_id'));
});

it('opens an owned notification with POST and allows read unread and read all', function () {
    $correction = ($this->requestCorrection)();
    $notification = $this->staffA->notifications()->sole();
    $this->actingAs($this->staffA)->get('/notifications/'.$notification->id.'/open')->assertStatus(405);
    expect($notification->fresh()->read_at)->toBeNull();
    $this->post('/notifications/'.$notification->id.'/open')->assertRedirect('/corrections/'.$correction->uuid);
    expect($notification->fresh()->read_at)->not->toBeNull();
    $this->from('/notifications')->patch('/notifications/'.$notification->id, ['read' => false])->assertRedirect('/notifications');
    expect($notification->fresh()->read_at)->toBeNull();
    $this->post('/notifications/read-all')->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull()->and($this->central->notifications()->sole()->read_at)->toBeNull();
});

it('cannot inspect or mutate another accounts notification', function () {
    ($this->requestCorrection)();
    $id = $this->staffA->notifications()->sole()->id;
    $this->actingAs($this->staffB)->post('/notifications/'.$id.'/open')->assertNotFound();
    $this->patch('/notifications/'.$id, ['read' => true])->assertNotFound();
    $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('entries.total', 0)->where('notifications.unread', 0));
    expect($this->staffA->notifications()->sole()->read_at)->toBeNull();
});

it('rechecks ownership permission and the current establishment for historical notifications', function (string $change) {
    ($this->requestCorrection)();
    $item = $this->staffA->notifications()->sole();
    match ($change) {
        'permission' => $this->staffA->deniedPermissions()->attach(Permission::findByName('corrections.view')),
        'establishment' => $this->staffA->forceFill(['establishment_id' => $this->b->id])->save(),
        'declaration' => $this->record->update(['status' => 'inactive']),
        'trash' => DB::table('students')->where('id', $this->student->id)->update(['deleted_at' => now()]),
    };
    $this->actingAs($this->staffA->fresh())->get('/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 0)->where('notifications.unread', 0)->has('notifications.recent', 0));
    $this->post('/notifications/'.$item->id.'/open')->assertNotFound();
    $this->patch('/notifications/'.$item->id, ['read' => true])->assertNotFound();
    $this->post('/notifications/read-all')->assertRedirect();
    expect($item->fresh()->read_at)->toBeNull();
})->with(['permission', 'establishment', 'declaration', 'trash']);

it('hides confirmation notifications after the student leaves the current establishment', function () {
    app(ConfirmStudentInformation::class)->handle($this->studentUser, StudentConfirmationContext::load($this->student->fresh())->forStudent()['version']);
    $this->record->update(['status' => 'inactive']);
    expect(NotificationInbox::feed($this->staffA)['unread'])->toBe(0)
        ->and(NotificationInbox::feed($this->central)['unread'])->toBe(1);
});

it('builds destinations from authorized subjects and never redirects to stored arbitrary URLs', function () {
    $correction = ($this->requestCorrection)();
    $item = $this->central->notifications()->sole();
    $item->update(['data' => [...$item->data, 'href' => '//attacker.example/steal']]);
    $this->actingAs($this->central)->post('/notifications/'.$item->id.'/open')->assertRedirect('/corrections/'.$correction->uuid);
});

it('validates and audits preferences without altering other accounts', function () {
    $this->actingAs($this->staffA)->from('/notifications')->put('/notifications/preferences', ['enabled' => [StudentActivity::INFORMATION_CONFIRMED]])->assertRedirect('/notifications');
    expect($this->staffA->fresh()->wantsNotification(StudentActivity::CORRECTION_REQUESTED))->toBeFalse()
        ->and($this->admin->fresh()->notification_preferences)->toBeNull();
    expect(Activity::query()->where('event', 'notification_preferences_updated')->sole()->properties->get('disabled_types'))->toBe([StudentActivity::CORRECTION_REQUESTED]);
    $this->put('/notifications/preferences', ['enabled' => ['forged']])->assertSessionHasErrors('enabled.0');
    $this->put('/notifications/preferences', ['enabled' => [StudentActivity::INFORMATION_CONFIRMED, StudentActivity::INFORMATION_CONFIRMED]])->assertSessionHasErrors();
    ($this->requestCorrection)();
    expect($this->staffA->notifications()->count())->toBe(0);
    $this->put('/notifications/preferences', ['enabled' => []])->assertSessionHasNoErrors();
    expect($this->staffA->fresh()->notification_preferences['off'])->toHaveCount(2);
});

it('filters and paginates on the server while the bell stays limited to eight rows', function () {
    ($this->requestCorrection)();
    $data = $this->central->notifications()->sole()->data;
    foreach (range(1, 30) as $index) {
        $this->central->notifications()->create(['id' => (string) Str::uuid(), 'type' => StudentActivity::class, 'data' => $data, 'read_at' => $index % 2 === 0 ? now() : null]);
    }
    $this->actingAs($this->central)->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('entries.total', 31)->has('entries.data', 25)->has('notifications.recent', 8));
    $this->get('/notifications?filter=read')->assertInertia(fn (Assert $page) => $page->where('entries.total', 15));
    $this->get('/notifications?filter=unread&kind=correction_requested')->assertInertia(fn (Assert $page) => $page->where('entries.total', 16));
    $this->get('/notifications?kind=information_confirmed')->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
    $this->get('/notifications?filter=invalid')->assertSessionHasErrors('filter');
    DB::enableQueryLog();
    DB::flushQueryLog();
    $feed = NotificationInbox::feed($this->central);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($feed['recent'])->toHaveCount(8)->and($queryCount)->toBeLessThanOrEqual(10);
});

it('protects notification routes by authentication portal and usable internal identity', function () {
    $this->get('/notifications')->assertRedirect(route('login'));
    $this->actingAs($this->studentUser)->get('/notifications')->assertForbidden();
    $this->get('http://'.config('portals.bourse_domain').'/notifications')->assertNotFound();
    $this->staffA->forceFill(['is_active' => false])->save();
    $this->actingAs($this->staffA)->get('/notifications')->assertRedirect(route('login'));
});

it('prunes only student notifications read more than ninety days ago', function () {
    $this->freezeTime();
    ($this->requestCorrection)();
    $old = $this->admin->notifications()->sole();
    $old->update(['read_at' => now()->subDays(91)]);
    $recent = $this->central->notifications()->sole();
    $recent->update(['read_at' => now()->subDays(89)]);
    $this->staffA->notifications()->sole()->update(['created_at' => now()->subDays(120)]);
    $other = $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'OtherNotification', 'data' => [], 'read_at' => now()->subDays(100)]);
    $this->artisan('notifications:prune-read')->assertSuccessful();
    expect($old->fresh())->toBeNull()->and($recent->fresh())->not->toBeNull()->and($other->fresh())->not->toBeNull()
        ->and($this->staffA->notifications()->count())->toBe(1);
});

it('bounds recipient lookup queries independently of the number of recipients', function () {
    foreach (range(1, 20) as $index) {
        User::factory()->create()->assignRole('central_scholarship_office');
    }
    DB::enableQueryLog();
    StaffNotifications::student($this->student, StudentActivity::INFORMATION_CONFIRMED, 'Confirmation de test', $this->student->uuid, 'students.view');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $selects = array_filter($queries, fn ($query) => str_starts_with(strtolower($query['query']), 'select'));
    expect(count($selects))->toBeLessThan(15)->and(DatabaseNotification::count())->toBe(23);
});

it('does not load student identities or permission scopes for an empty inbox', function () {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $feed = NotificationInbox::feed($this->central);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($feed)->toBe(['unread' => 0, 'recent' => []])->and(count($queries))->toBeLessThanOrEqual(2);
    expect(collect($queries)->contains(fn ($query) => preg_match('/from\s+[`"]?students[`"]?/i', $query['query']) === 1))->toBeFalse();
});
