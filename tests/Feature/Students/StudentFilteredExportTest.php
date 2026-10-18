<?php

use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true]);
    $this->establishment = Establishment::factory()->create();
    $this->otherEstablishment = Establishment::factory()->create();
    $this->level = Level::factory()->create();
    $this->mention = Mention::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->program = Program::factory()->create(['establishment_id' => $this->establishment->id, 'mention_id' => $this->mention->id]);
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->staff = User::factory()->create(['establishment_id' => $this->establishment->id])->assignRole('establishment');
    $this->scope = ['academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id];
    foreach ([
        ['CENTRAL_PENDING', 'central', 'pending', 'pending', false],
        ['CENTRAL_MATCHED', 'central', 'pending', 'matched', false],
        ['CENTRAL_INACTIVE', 'central', 'inactive', 'verified', false],
        ['PNES', 'pnes', 'active', 'verified', true],
        ['ESTABLISHMENT', 'establishment', 'active', 'verified', false],
        ['PREINSCRIPTION', 'preinscription', 'active', 'verified', false],
    ] as [$name, $source, $status, $verification, $holder]) {
        StudentRecord::factory()->create([
            ...$this->scope, 'level_id' => $this->level->id, 'program_id' => $this->program->id,
            'student_id' => Student::factory()->create(['last_name' => $name])->id,
            'source' => $source, 'status' => $status, 'verification' => $verification, 'is_scholarship_holder' => $holder,
        ]);
    }
    $this->foreign = StudentRecord::factory()->create([
        'academic_year_id' => $this->year->id, 'establishment_id' => $this->otherEstablishment->id,
        'student_id' => Student::factory()->create(['last_name' => 'FOREIGN'])->id,
        'source' => 'central', 'status' => 'pending', 'verification' => 'pending',
    ]);
});

/** Read actual exported identities, rather than relying only on audit counts. */
function filteredExportNames(TestResponse $response): array
{
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $path = tempnam(sys_get_temp_dir(), 'umg-filtered-export-');
    $book = null;
    try {
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $names = [];
        for ($row = 2; filled($name = $book->getActiveSheet()->getCell('A'.$row)->getValue()); $row++) {
            $names[] = (string) $name;
        }
        sort($names);

        return $names;
    } finally {
        $book?->disconnectWorksheets();
        unlink($path);
    }
}

it('exports each origin including pending central entries', function (string $source, array $expected) {
    $response = $this->actingAs($this->central)->get('/students/export?'.http_build_query([...$this->scope, 'source' => $source]));
    expect(filteredExportNames($response))->toBe($expected);
    $log = Activity::query()->where('event', 'students_exported')->sole();
    expect($log->properties['total_rows'])->toBe(count($expected))->and($log->properties['filters'])->toContain('source');
})->with([
    'central entry' => ['central', ['CENTRAL_INACTIVE', 'CENTRAL_MATCHED', 'CENTRAL_PENDING']],
    'PNES' => ['pnes', ['PNES']],
    'establishment list' => ['establishment', ['ESTABLISHMENT']],
    'pre-registration results' => ['preinscription', ['PREINSCRIPTION']],
]);

it('exports every listed status unless the user filters it', function (?string $status, array $expected) {
    $filters = array_filter([...$this->scope, 'status' => $status]);
    expect(filteredExportNames($this->actingAs($this->central)->get('/students/export?'.http_build_query($filters))))->toBe($expected);
})->with([
    'all' => [null, ['CENTRAL_INACTIVE', 'CENTRAL_MATCHED', 'CENTRAL_PENDING', 'ESTABLISHMENT', 'PNES', 'PREINSCRIPTION']],
    'pending' => ['pending', ['CENTRAL_MATCHED', 'CENTRAL_PENDING']],
    'inactive' => ['inactive', ['CENTRAL_INACTIVE']],
    'active' => ['active', ['ESTABLISHMENT', 'PNES', 'PREINSCRIPTION']],
]);

it('combines the origin and all applied filters exactly as the list does without mutating situations', function () {
    $filters = [...$this->scope, 'source' => 'central', 'status' => 'pending', 'verification' => 'matched', 'holder' => 'no',
        'level_id' => $this->level->id, 'mention_id' => $this->mention->id, 'program_id' => $this->program->id, 'search' => 'CENTRAL'];
    $this->actingAs($this->central)->get('/students?'.http_build_query(['view' => 'list', ...$filters]))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.student.last_name', 'CENTRAL_MATCHED'));
    expect(filteredExportNames($this->get('/students/export?'.http_build_query($filters))))->toBe(['CENTRAL_MATCHED']);
    $record = StudentRecord::query()->whereHas('student', fn ($q) => $q->where('last_name', 'CENTRAL_MATCHED'))->sole();
    expect($record->status->value)->toBe('pending')->and($record->verification->value)->toBe('matched');
    $log = Activity::query()->where('event', 'students_exported')->sole();
    expect($log->properties->toJson())->not->toContain('CENTRAL');
});

it('exports all matching pages and ignores situations from other years', function () {
    StudentRecord::factory()->count(26)->create([...$this->scope, 'source' => 'central', 'status' => 'pending', 'verification' => 'pending']);
    StudentRecord::factory()->create(['establishment_id' => $this->establishment->id, 'academic_year_id' => AcademicYear::factory()->create()->id]);
    $this->actingAs($this->central)->get('/students?'.http_build_query(['view' => 'list', ...$this->scope]))
        ->assertInertia(fn (Assert $page) => $page->where('records.total', 32)->has('records.data', 25));
    expect(filteredExportNames($this->get('/students/export?'.http_build_query([...$this->scope, 'page' => 2]))))->toHaveCount(32);
});

it('keeps the establishment perimeter on filtered and selected exports', function () {
    $this->actingAs($this->staff);
    expect(filteredExportNames($this->get('/students/export?source=central')))->toBe(['CENTRAL_INACTIVE', 'CENTRAL_MATCHED', 'CENTRAL_PENDING']);
    $this->get('/students/export?establishment_id='.$this->otherEstablishment->id)->assertForbidden();
    $this->get('/students/template?establishment_id='.$this->otherEstablishment->id)->assertForbidden();
    expect(filteredExportNames($this->post('/students/export', [...$this->scope, 'records' => [$this->foreign->uuid]])))->toBe([]);
});

it('requires the export permission and a single academic year', function () {
    $viewer = User::factory()->create()->assignRole('central_scholarship_office');
    $viewer->deniedPermissions()->attach(Permission::findByName('students.export', 'web'));
    $this->actingAs($viewer)->get('/students/export?source=central')->assertForbidden();
    $this->get('/students/template')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/students/export')->assertForbidden();
    $this->actingAs($this->central)->get('/students/export?academic_year_id=all')->assertNotFound();
});

it('downloads a blank template independently of the filtered results', function () {
    $this->actingAs($this->central);
    $response = $this->get('/students/template?'.http_build_query($this->scope));
    expect(filteredExportNames($response))->toBe([]);
    expect($response->headers->get('content-disposition'))->toContain('modele-etudiants');
    expect(Activity::query()->where('event', 'students_exported')->count())->toBe(0);
    expect(filteredExportNames($this->get('/students/export?'.http_build_query([...$this->scope, 'source' => 'central']))))
        ->toBe(['CENTRAL_INACTIVE', 'CENTRAL_MATCHED', 'CENTRAL_PENDING']);
});

it('keeps the blank template available without a current academic year', function () {
    AcademicYear::query()->update(['is_current' => false]);
    expect(filteredExportNames($this->actingAs($this->staff)->get('/students/template')))->toBe([]);
});
