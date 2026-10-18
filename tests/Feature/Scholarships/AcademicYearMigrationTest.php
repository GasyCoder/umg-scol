<?php

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

function academicYearMigration(): Migration
{
    return require database_path('migrations/2026_10_19_120000_replace_scholarship_campaigns_with_academic_years.php');
}

beforeEach(function () {
    // SQLite cannot rebuild referenced tables inside RefreshDatabase’s transaction.
    // Use a separate, disposable connection so the populated migration is exercised as in production.
    config(['database.connections.migration_check' => config('database.connections.sqlite'), 'database.default' => 'migration_check']);
    config(['database.connections.migration_check.database' => ':memory:']);
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    academicYearMigration()->down();
    $this->year = AcademicYear::factory()->create(['name' => '2026-2027', 'is_current' => true]);
    $this->actor = User::factory()->create();
});

afterEach(function () {
    DB::purge('migration_check');
});

function legacyCampaign(int $year, int $sequence = 0): int
{
    return DB::table('scholarship_campaigns')->insertGetId([
        'uuid' => (string) Str::uuid7(), 'academic_year_id' => $year,
        'name' => (string) Str::uuid7(), 'status' => 'open', 'last_application_sequence' => $sequence,
    ]);
}

function legacyApplication(int $campaign, int $year, int $actor, int $sequence = 1, ?string $ddb = 'DDB-2026-000001', ?Student $student = null, bool $trashed = false): int
{
    $student ??= Student::factory()->create();
    $record = StudentRecord::query()->where('student_id', $student->id)->where('academic_year_id', $year)->first()
        ?? StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $year]);

    return DB::table('scholarship_applications')->insertGetId([
        'uuid' => (string) Str::uuid7(), 'application_number' => 'BRS'.$campaign.'-'.$sequence,
        'scholarship_campaign_id' => $campaign, 'sequence' => $sequence,
        'student_id' => $student->id, 'student_record_id' => $record->id,
        'ddb_number' => $ddb, 'ddb_key' => $ddb, 'received_by' => $actor,
        'status' => 'pending', 'received_at' => now(), 'deleted_at' => $trashed ? now() : null,
    ]);
}

it('preserves files, identifiers, checks, history and the sequence high water mark', function () {
    $campaign = legacyCampaign($this->year->id, 40);
    $id = legacyApplication($campaign, $this->year->id, $this->actor->id, 7, 'DDB-2026-000007');
    $before = DB::table('scholarship_applications')->find($id);
    DB::table('scholarship_application_checks')->insert(['scholarship_application_id' => $id, 'key' => 'identity', 'status' => 'pending']);
    DB::table('scholarship_application_status_histories')->insert(['scholarship_application_id' => $id, 'to_status' => 'pending', 'actor_id' => $this->actor->id, 'created_at' => now()]);

    academicYearMigration()->up();

    $after = DB::table('scholarship_applications')->find($id);
    expect(Schema::hasTable('scholarship_campaigns'))->toBeFalse()
        ->and(Schema::hasColumn('scholarship_applications', 'scholarship_campaign_id'))->toBeFalse()
        ->and($after->academic_year_id)->toBe($this->year->id)
        ->and($after->uuid)->toBe($before->uuid)
        ->and($after->ddb_number)->toBe($before->ddb_number)
        ->and($after->application_number)->toBe($before->application_number)
        ->and($after->sequence)->toBe(7)
        ->and($this->year->fresh()->last_application_sequence)->toBe(40)
        ->and(DB::table('scholarship_application_checks')->count())->toBe(1)
        ->and(DB::table('scholarship_application_status_histories')->count())->toBe(1);

    academicYearMigration()->down();
    expect(DB::table('scholarship_applications')->find($id)->application_number)->toBe($before->application_number)
        ->and(DB::table('scholarship_campaigns')->sole()->last_application_sequence)->toBe(40);
    academicYearMigration()->up();
    expect($this->year->fresh()->last_application_sequence)->toBe(40);
});

it('merges numbering ranges and keeps counters even for campaigns with no remaining files', function () {
    $one = legacyCampaign($this->year->id, 10);
    $two = legacyCampaign($this->year->id, 5);
    legacyCampaign($this->year->id, 8);
    $first = legacyApplication($one, $this->year->id, $this->actor->id, 3, 'DDB-A');
    $second = legacyApplication($two, $this->year->id, $this->actor->id, 2, 'DDB-B');
    academicYearMigration()->up();
    expect(DB::table('scholarship_applications')->find($first)->sequence)->toBe(3)
        ->and(DB::table('scholarship_applications')->find($second)->sequence)->toBe(12)
        ->and($this->year->fresh()->last_application_sequence)->toBe(23);
});

it('refuses conflicting live students or DDB keys before changing the schema', function (string $conflict) {
    $student = Student::factory()->create();
    $one = legacyCampaign($this->year->id);
    $two = legacyCampaign($this->year->id);
    legacyApplication($one, $this->year->id, $this->actor->id, student: $student);
    legacyApplication($two, $this->year->id, $this->actor->id, ddb: $conflict === 'ddb' ? 'DDB-2026-000001' : 'OTHER', student: $conflict === 'student' ? $student : null);
    expect(fn () => academicYearMigration()->up())->toThrow(RuntimeException::class, 'Suppression des campagnes impossible');
    expect(Schema::hasTable('scholarship_campaigns'))->toBeTrue()
        ->and(Schema::hasColumn('academic_years', 'last_application_sequence'))->toBeFalse()
        ->and(DB::table('scholarship_applications')->count())->toBe(2);
})->with(['student', 'ddb']);

it('preserves trashed replacements and enforces one live file per student and year in SQL', function () {
    $student = Student::factory()->create();
    $one = legacyCampaign($this->year->id);
    $two = legacyCampaign($this->year->id);
    $old = legacyApplication($one, $this->year->id, $this->actor->id, ddb: 'OLD', student: $student, trashed: true);
    $live = legacyApplication($two, $this->year->id, $this->actor->id, ddb: 'NEW', student: $student);
    academicYearMigration()->up();
    expect(DB::table('scholarship_applications')->count())->toBe(2);
    expect(fn () => DB::table('scholarship_applications')->where('id', $old)->update(['deleted_at' => null]))->toThrow(QueryException::class);
    expect(fn () => academicYearMigration()->down())->toThrow(RuntimeException::class, 'Retour aux campagnes impossible');
    expect(Schema::hasTable('scholarship_campaigns'))->toBeFalse();
    DB::table('scholarship_applications')->where('id', $live)->update(['deleted_at' => now()]);
    DB::table('scholarship_applications')->where('id', $old)->update(['deleted_at' => null]);
    expect(DB::table('scholarship_applications')->whereNull('deleted_at')->sole()->id)->toBe($old);
});

it('removes obsolete permissions and cached campaign tokens', function () {
    $permission = Permission::findOrCreate('campaigns.view', 'web');
    $role = Role::findOrCreate('superadmin', 'web');
    $role->givePermissionTo($permission);
    $this->actor->givePermissionTo($permission);
    DB::table('platform_settings')->insert(['key' => 'ddb_pattern', 'value' => 'DDB-{CAMPAGNE}-{NUM}']);
    Cache::forever('platform.ddb', ['ddb_pattern' => 'DDB-{CAMPAGNE}-{NUM}']);
    academicYearMigration()->up();
    expect(Permission::where('name', 'campaigns.view')->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('permission_id', $permission->id)->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->where('permission_id', $permission->id)->exists())->toBeFalse()
        ->and(DB::table('platform_settings')->where('key', 'ddb_pattern')->value('value'))->toBe('DDB--{NUM}')
        ->and(Cache::has('platform.ddb'))->toBeFalse();
});
