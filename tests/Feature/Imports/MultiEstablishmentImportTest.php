<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Enums\RepeatCode;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\ScholarshipEligibility;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
 * File covering several establishments, in the format of the user's example (fictitious people):
 * the establishment of each row is given by "Institution" (name) and "Institution Code" (acronym).
 */
const MULTI_HEADERS = ['Nom', 'Prenom', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Code Redoublement'];

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'scholarship_max_bac_year' => 2025]);
    $this->facmed = Establishment::factory()->create(['code' => 'FACMED', 'name' => 'Faculté de Médecine']);
    $this->iugm = Establishment::factory()->create(['code' => 'IUGMMAH', 'name' => 'IUGM Mahajanga']);
    $this->isstm = Establishment::factory()->create(['code' => 'ISSTM', 'name' => 'Institut Supérieur des Sciences et Technologies de Mahajanga']);
    Level::factory()->create(['code' => 'L1', 'name' => 'Licence 1']);
    $this->admin = User::factory()->create()->assignRole('superadmin');
});

/** @param list<list<mixed>> $rows */
function uploadMulti(TestCase $test, User $actor, array $rows, ?int $establishment = null): StudentImport
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([MULTI_HEADERS, ...$rows]);
    $path = tempnam(sys_get_temp_dir(), 'multi').'.xlsx';
    (new Xlsx($book))->save($path);
    $test->actingAs($actor)->post('/imports', array_filter(['file' => new UploadedFile($path, 'etudiants.xlsx', null, null, true), 'establishment_id' => $establishment, 'academic_year_id' => $test->year->id], fn ($value) => $value !== null))->assertSessionHasNoErrors();

    return StudentImport::query()->latest('id')->firstOrFail();
}

/** @return list<mixed> */
function multiRow(string $name, string $cin, string $phone, string $institution, string $code, string $mention, string $code_redoublement = 'PASSANT'): array
{
    return [$name, 'Test', '12/10/2007', 'MERE Exemple', $cin, $phone, strtolower($name).'@example.test', $institution, $code, $mention, $mention, 'L1', $code_redoublement];
}

it('imports one file for several establishments, each row in its own establishment', function () {
    $import = uploadMulti($this, $this->admin, [
        multiRow('RANDRIA', '410052015939', '0378725137', 'Faculté de Médecine', 'FACMED', 'Médecine Humaine'),
        multiRow('MORA', '711991088852', '0379401335', 'Faculté de Médecine', 'FACMED', 'Médecine Humaine'),
        multiRow('RAKOTO', '401031090068', '0340590797', 'IUGM Mahajanga', 'IUGMMAH', 'Finance'),
        multiRow('RADAMA', '401012091420', '0327192849', 'ISSTM', 'ISSTM', 'STNPA'),
    ]);
    expect($import->establishment_id)->toBeNull()->and($import->mapping)->toHaveKeys(['establishment', 'establishment_code', 'first_names', 'mother_name', 'cin', 'phone', 'email', 'repeat_code'])
        ->and($import->mapping)->not->toHaveKey('sector');

    app(AnalyzeStudentImport::class)->handle($this->admin, $import, $import->mapping);
    expect($import->refresh()->valid_rows)->toBe(4)
        ->and($import->reference_changes['mentions'])->toBe(['FACMED › Médecine Humaine', 'IUGMMAH › Finance', 'ISSTM › STNPA']);

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(StudentRecord::query()->where('establishment_id', $this->facmed->id)->count())->toBe(2)
        ->and(StudentRecord::query()->where('establishment_id', $this->iugm->id)->count())->toBe(1)
        ->and(StudentRecord::query()->where('establishment_id', $this->isstm->id)->count())->toBe(1)
        ->and(StudentRecord::query()->pluck('repeat_code')->unique()->all())->toBe([RepeatCode::Passing])
        ->and(Mention::query()->where('establishment_id', $this->iugm->id)->sole()->name)->toBe('Finance')
        ->and(Program::query()->where('establishment_id', $this->isstm->id)->sole()->mention->name)->toBe('STNPA')
        ->and(StudentRecord::query()->whereNull('program_id')->exists())->toBeFalse();
});

it('rejects a row whose establishment is unknown and names it in the summary', function () {
    $import = uploadMulti($this, $this->admin, [
        multiRow('RANDRIA', '410052015939', '0378725137', 'Faculté Inconnue', 'XXX', 'Médecine Humaine'),
        multiRow('MORA', '711991088852', '0379401335', 'Faculté de Médecine', 'FACMED', 'Médecine Humaine'),
    ]);
    app(AnalyzeStudentImport::class)->handle($this->admin, $import, $import->mapping);
    $first = $import->rows()->orderBy('row_number')->first();
    expect($import->refresh()->valid_rows)->toBe(1)
        ->and(collect($first->errors)->pluck('code')->all())->toContain('unknown_establishment')
        ->and($import->issue_summary[0])->toMatchArray(['field' => 'establishment_code', 'code' => 'unknown_establishment', 'values' => ['XXX']]);
});

it('requires an establishment column for a file covering several establishments', function () {
    $import = uploadMulti($this, $this->admin, [multiRow('RANDRIA', '410052015939', '0378725137', 'Faculté de Médecine', 'FACMED', 'Médecine Humaine')]);
    $mapping = collect($import->mapping)->except(['establishment', 'establishment_code'])->all();

    $this->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $mapping])->assertSessionHasErrors('mapping');
});

it('keeps an establishment account on its own establishment, the code column included', function () {
    $user = User::factory()->create(['establishment_id' => $this->facmed->id])->assignRole('establishment');
    $import = uploadMulti($this, $user, [
        multiRow('RANDRIA', '410052015939', '0378725137', 'Faculté de Médecine', 'FACMED', 'Médecine Humaine'),
        multiRow('RAKOTO', '401031090068', '0340590797', 'IUGM Mahajanga', 'IUGMMAH', 'Finance'),
    ]);
    expect($import->establishment_id)->toBe($this->facmed->id);
    app(AnalyzeStudentImport::class)->handle($user, $import, $import->mapping);

    expect($import->refresh()->valid_rows)->toBe(1)
        ->and(collect($import->rows()->orderBy('row_number')->get()[1]->errors)->pluck('code')->all())->toContain('other_establishment');
});

it('reads "PASSANT" as the passing code, eligible at rate 1 like a new student', function () {
    expect(RepeatCode::parse('PASSANT'))->toBe(RepeatCode::Passing)->and(RepeatCode::parse('P (Passant)'))->toBe(RepeatCode::Passing)
        ->and(RepeatCode::parse('redoublant'))->toBe(RepeatCode::Repeating);
    $record = StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $this->facmed->id, 'repeat_code' => 'P', 'scholarship_request_reference' => 'R-1']);
    $record->student->update(['nationality' => 'Malagasy', 'bac_year' => 2024]);

    expect(ScholarshipEligibility::evaluate($record->student->fresh(), $record->load('academicYear')))->toMatchArray(['status' => 'eligible', 'rate' => '1']);
});
