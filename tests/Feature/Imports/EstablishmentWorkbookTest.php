<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Exports\Students\EstablishmentTemplateWorkbook as Workbook;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Domain;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->establishment = Establishment::factory()->create(['code' => 'ETST', 'name' => 'Ecole de Test', 'sector' => 'public']);
    $this->year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_current' => true, 'scholarship_max_bac_year' => 2024]);
    $this->level = Level::factory()->create(['code' => 'L2', 'name' => 'Licence 2']);
    $domain = Domain::factory()->create(['code' => 'ALSH', 'name' => 'Arts, Lettres et Sciences Humaines']);
    $mention = Mention::factory()->create(['establishment_id' => $this->establishment->id, 'domain_id' => $domain->id, 'code' => null, 'name' => 'Droit']);
    $this->program = Program::factory()->create(['establishment_id' => $this->establishment->id, 'mention_id' => $mention->id, 'code' => null, 'name' => 'Tronc Commun', 'training_type' => 'academic']);
    $this->actor = User::factory()->create(['establishment_id' => $this->establishment->id])->assignRole('establishment');
});

/** Opens a downloaded workbook. */
function workbookFrom(TestResponse $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return IOFactory::load($path);
}

it('downloads the empty establishment file with the official columns, lists and formulas', function () {
    $response = $this->actingAs($this->actor)->get('/imports/template')->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($response->headers->get('content-disposition'))->toContain('modele-etudiants-etst.xlsx');
    $book = workbookFrom($response);
    $sheet = $book->getSheet(0);

    expect($sheet->rangeToArray('A1:Z1')[0])->toBe(array_values(Workbook::HEADERS))
        ->and($sheet->getStyle('Z1')->getFill()->getStartColor()->getRGB())->toBe('00B0F0')
        ->and($sheet->getStyle('T1')->getFill()->getStartColor()->getRGB())->toBe('FF0000')
        ->and((string) $sheet->getCell('M2')->getValue())->toContain('J2<=2024')
        ->and($sheet->getCell('A2')->getValue())->toBeNull()
        ->and($sheet->getStyle('F2')->getNumberFormat()->getFormatCode())->toBe('@');
    $lists = $book->getSheetByName('Listes');
    expect($lists?->getSheetState())->toBe(Worksheet::SHEETSTATE_HIDDEN);
    $values = collect($lists->toArray())->flatten()->filter()->values();
    expect($values)->toContain('Ecole de Test (ETST)', '(ALSH) Arts, Lettres et Sciences Humaines', 'Tronc Commun', 'Droit', 'L2', 'R (Redoublant)', 'Publique', 'Académique');
    expect($sheet->getCell('C10')->getDataValidation()->getFormula1())->toBe('"M,F"')
        ->and($sheet->getCell('Z10')->getDataValidation()->getFormula1())->toStartWith('Listes!');
});

it('reads the empty model as a file without students: formula-only lines are not rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    file_put_contents($path, $this->actingAs($this->actor)->get('/imports/template')->streamedContent());

    $this->post('/imports', ['file' => new UploadedFile($path, 'modele.xlsx', null, null, true), 'establishment_id' => $this->establishment->id, 'academic_year_id' => $this->year->id])
        ->assertSessionHasErrors(['file' => 'Le fichier ne contient aucune ligne.']);
    expect(StudentImport::query()->count())->toBe(0);
});

it('exports the declarations of the selection in the establishment file format, audited', function () {
    $student = Student::factory()->create(['last_name' => 'RAKOTO', 'first_names' => 'Test', 'sex' => 'F', 'birth_date' => '2005-03-04', 'cin' => '413012049888', 'phone' => '0327407096', 'nationality' => 'Malagasy', 'bac_year' => 2023, 'bac_series' => 'A2']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'repeat_code' => 'R', 'scholarship_requested_at' => '2026-02-05', 'scholarship_request_reference' => '8']);
    $other = Establishment::factory()->create();
    StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $other->id, 'level_id' => $this->level->id]);

    $response = $this->actingAs($this->actor)->get('/students/export?academic_year_id='.$this->year->id)->assertOk();
    $sheet = workbookFrom($response)->getSheet(0);

    expect($sheet->rangeToArray('A2:Z2', null, false, false)[0])->toBe([
        'RAKOTO', 'Test', 'F', $sheet->getCell('D2')->getValue(), null, '413012049888', null, null, 'Malagasy', 2023, 'A2', 'R (Redoublant)',
        'OUI', '1/4', $sheet->getCell('O2')->getValue(), '8', null, '0327407096', $student->email, 'Publique', '(ALSH) Arts, Lettres et Sciences Humaines',
        'Droit', 'Tronc Commun', 'Académique', 'L2', 'Ecole de Test (ETST)',
    ])->and($sheet->getCell('D2')->getFormattedValue())->toBe('04/03/2005')
        ->and($sheet->getCell('A3')->getValue())->toBeNull()
        ->and($sheet->getCell('A5')->getValue())->toBe('Arrêté au nombre de:');
    expect(Activity::query()->where('event', 'students_exported')->sole()->properties['total_rows'])->toBe(1);
});

it('re-imports an exported file as is', function () {
    $students = Student::factory()->count(3)->create(['nationality' => 'Malagasy', 'bac_year' => 2022]);
    foreach ($students as $index => $student) {
        StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'repeat_code' => 'N', 'scholarship_requested_at' => '2026-02-0'.($index + 1), 'scholarship_request_reference' => (string) ($index + 1)]);
    }
    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    file_put_contents($path, $this->actingAs($this->actor)->get('/students/export?academic_year_id='.$this->year->id)->streamedContent());
    $next = AcademicYear::factory()->create(['name' => '2026-2027']);

    $this->post('/imports', ['file' => new UploadedFile($path, 'export.xlsx', null, null, true), 'establishment_id' => $this->establishment->id, 'academic_year_id' => $next->id])->assertRedirect();
    $import = StudentImport::query()->sole();
    expect($import->total_rows)->toBe(3)->and($import->mapping)->toHaveCount(24);
    app(AnalyzeStudentImport::class)->handle($this->actor, $import, $import->mapping);

    expect($import->refresh()->valid_rows)->toBe(3)
        ->and($import->rows()->pluck('matched_student_id')->sort()->values()->all())->toBe($students->modelKeys());
});

it('refuses the export without the export permission', function () {
    $this->actor->revokePermissionTo('students.export');
    $role = $this->actor->roles->first();
    $role->revokePermissionTo('students.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->actor->fresh())->get('/students/export')->assertForbidden();
});
