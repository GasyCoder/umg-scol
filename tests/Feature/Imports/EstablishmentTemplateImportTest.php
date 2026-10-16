<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Enums\RepeatCode;
use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Enums\TrainingType;
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
use App\Support\StudentImportColumns;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
 * Synthetic copy of the official establishment file (modèle établissement): same headers, legend row,
 * numeric cells, Excel serial dates, formula columns and summary block. Every person is fictitious.
 */
const TEMPLATE_HEADERS = ['NOM', 'PRENOMS', 'SEXE', 'DATE DE NAISSANCE', 'Nom et prénom de mère', 'CIN', 'Date delivrabce', 'Lieu de délivrance', 'NATIONALITÉ', 'ANNÉE D’OBTENTION DU BACC', 'SÉRIE DU BACC', 'CODE DE REDOUBLEMENT', 'BOURSIER', 'TAUX DE BOURSE', 'DATE DE DEPÔT DU DEMANDE DE BOURSE', 'REFERENCE DEMANDE DE BOURSE', 'ADRESSE EXACTE', 'NUMERO TELEPHONE', 'ADRESSE MAIL', 'Institution', 'Domaine', 'Mention', 'Parcours', 'Type de formation', 'Niveau', 'Ecole / Institut'];

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->establishment = Establishment::factory()->create(['code' => 'ETST', 'name' => 'Ecole de Test', 'sector' => 'public']);
    $this->year = AcademicYear::factory()->create(['scholarship_max_bac_year' => 2025]);
    $this->level = Level::factory()->create(['code' => 'L1', 'name' => 'Licence 1']);
    $this->alsh = Domain::factory()->create(['code' => 'ALSH', 'name' => 'Arts, Lettres et Sciences Humaines']);
    Domain::factory()->create(['code' => 'STS', 'name' => 'Sciences, Technologies et Santé']);
    $this->letters = Mention::factory()->create(['establishment_id' => $this->establishment->id, 'domain_id' => $this->alsh->id, 'code' => null, 'name' => 'Lettres modernes']);
    $this->history = Mention::factory()->create(['establishment_id' => $this->establishment->id, 'domain_id' => $this->alsh->id, 'code' => null, 'name' => 'Histoire']);
    // The same program name exists in two mentions: only the mention tells them apart.
    $this->lettersCommon = Program::factory()->create(['establishment_id' => $this->establishment->id, 'mention_id' => $this->letters->id, 'code' => null, 'name' => 'Tronc Commun', 'training_type' => 'academic']);
    $this->historyCommon = Program::factory()->create(['establishment_id' => $this->establishment->id, 'mention_id' => $this->history->id, 'code' => null, 'name' => 'Tronc Commun', 'training_type' => 'academic']);
    $this->actor = User::factory()->create()->assignRole('establishment');
    $this->actor->forceFill(['establishment_id' => $this->establishment->id])->save();
    $this->actingAs($this->actor);
});

function excelDate(string $date): float
{
    return (float) Date::PHPToExcel(new DateTimeImmutable($date));
}

/**
 * One data line of the template, keyed by header; values follow the cell types of the official file.
 *
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function templateRow(array $overrides = []): array
{
    return [
        'NOM' => 'RANDRIA', 'PRENOMS' => 'Test Un', 'SEXE' => 'M', 'DATE DE NAISSANCE' => excelDate('2003-05-12'),
        'Nom et prénom de mère' => 'RASOA Exemple', 'CIN' => 413012049888, 'Date delivrabce' => excelDate('2021-08-02'),
        'Lieu de délivrance' => 'Ville Exemple', 'NATIONALITÉ' => 'Malagasy', 'ANNÉE D’OBTENTION DU BACC' => 2022, 'SÉRIE DU BACC' => 'A2',
        'CODE DE REDOUBLEMENT' => 'N (Nouveau)', 'BOURSIER' => null, 'TAUX DE BOURSE' => null,
        'DATE DE DEPÔT DU DEMANDE DE BOURSE' => excelDate('2026-10-05'), 'REFERENCE DEMANDE DE BOURSE' => 'DB-0001',
        'ADRESSE EXACTE' => 'Lot Exemple 1', 'NUMERO TELEPHONE' => 327407096, 'ADRESSE MAIL' => 'test.un@example.test',
        'Institution' => 'Publique', 'Domaine' => '(ALSH) Arts, Lettres et Sciences Humaines', 'Mention' => 'Lettres modernes',
        'Parcours' => 'Tronc Commun', 'Type de formation' => 'Académique', 'Niveau' => 'L1', 'Ecole / Institut' => 'Ecole de Test (ETST)',
        ...$overrides,
    ];
}

/** @param list<array<string,mixed>> $rows */
function uploadTemplate(TestCase $test, array $rows): StudentImport
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray([TEMPLATE_HEADERS, array_map(fn (int $i) => '[ '.$i.' ]', range(1, count(TEMPLATE_HEADERS)))], null, 'A1', true);
    $line = 3;
    foreach ($rows as $row) {
        $sheet->fromArray([array_map(fn (string $header) => $row[$header] ?? null, TEMPLATE_HEADERS)], null, 'A'.$line, true);
        // BOURSIER and TAUX DE BOURSE are formulas in the official file: never imported.
        $sheet->setCellValue('M'.$line, '=IF(AND(I'.$line.'="Malagasy",P'.$line.'<>""),"OUI","NON")');
        $sheet->setCellValue('N'.$line, '=IF(M'.$line.'="OUI",IF(LEFT(L'.$line.',1)="N",1,0.25),0)');
        foreach (['D', 'G', 'O'] as $column) {
            $sheet->getStyle($column.$line)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }
        $line++;
    }
    // Summary block under an empty line: totals, not students.
    $sheet->fromArray([['Arrêté au nombre de: '.count($rows).' étudiants'], ['Dont boursiers', '=COUNTIF(M3:M'.($line - 1).',"OUI")'], ['Le Directeur', 'Signature']], null, 'A'.($line + 1));
    $path = tempnam(sys_get_temp_dir(), 'template-import-');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();
    try {
        $test->post('/imports', ['file' => new UploadedFile($path, 'modele-etablissement.xlsx', null, null, true), 'academic_year_id' => $test->year->id])->assertSessionHasNoErrors()->assertRedirect();
    } finally {
        unlink($path);
    }

    return StudentImport::query()->latest('id')->firstOrFail();
}

/** @param list<array<string,mixed>> $rows */
function analyzeTemplate(TestCase $test, array $rows): StudentImport
{
    $import = uploadTemplate($test, $rows);
    app(AnalyzeStudentImport::class)->handle($test->actor, $import, $import->mapping);

    return $import->fresh();
}

function rowErrorCodes(StudentImport $import, int $index = 0): array
{
    return collect($import->rows()->orderBy('row_number')->get()[$index]->errors)->map(fn ($error) => $error['field'].':'.$error['code'])->all();
}

it('maps the 24 columns of the official file and skips the legend, formulas and summary block', function () {
    $import = uploadTemplate($this, [templateRow(), templateRow(['NOM' => 'RAKOTO', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'test.deux@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002'])]);
    expect($import->mapping)->toHaveCount(24)->toBe([
        'last_name' => 0, 'first_names' => 1, 'sex' => 2, 'birth_date' => 3, 'mother_name' => 4, 'cin' => 5, 'cin_issued_at' => 6, 'cin_issued_place' => 7,
        'nationality' => 8, 'bac_year' => 9, 'bac_series' => 10, 'repeat_code' => 11, 'scholarship_requested_at' => 14, 'scholarship_request_reference' => 15,
        'address' => 16, 'phone' => 17, 'email' => 18, 'sector' => 19, 'domain' => 20, 'mention' => 21, 'program' => 22, 'training_type' => 23, 'level' => 24, 'establishment' => 25,
    ])->and($import->total_rows)->toBe(2)->and($import->rows()->pluck('row_number')->all())->toBe([3, 4]);
    expect($import->mapping)->not->toHaveKey('BOURSIER')->and(array_keys($import->mapping))->toContain(...StudentImportColumns::required());
});

it('imports the declarations of the official file with their repeat code and scholarship request', function () {
    $import = analyzeTemplate($this, [
        templateRow(),
        // Non-standard CIN (receipt), text dates j/m/aaaa, repeating student of another mention with the same program name.
        templateRow(['NOM' => 'RAKOTO', 'PRENOMS' => 'Test Deux', 'SEXE' => 'F', 'CIN' => 'RECU 2024-17', 'Date delivrabce' => null, 'DATE DE NAISSANCE' => '7/1/2002',
            'CODE DE REDOUBLEMENT' => 'R (Redoublant)', 'DATE DE DEPÔT DU DEMANDE DE BOURSE' => '05/10/2026', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002',
            'NUMERO TELEPHONE' => '+261 34 00 000 02', 'ADRESSE MAIL' => 'test.deux@example.test', 'Mention' => 'Histoire', 'Domaine' => 'ALSH']),
        // No scholarship request and no optional column.
        templateRow(['NOM' => 'RABE', 'PRENOMS' => 'Test Trois', 'CIN' => null, 'Date delivrabce' => null, 'Lieu de délivrance' => null, 'DATE DE DEPÔT DU DEMANDE DE BOURSE' => null,
            'REFERENCE DEMANDE DE BOURSE' => null, 'NUMERO TELEPHONE' => null, 'ADRESSE MAIL' => null, 'Institution' => null, 'Domaine' => null, 'Type de formation' => null, 'Ecole / Institut' => null,
            'CODE DE REDOUBLEMENT' => 'T+ (Triplant ou plus)', 'NATIONALITÉ' => 'Comorien']),
    ]);
    expect($import->total_rows)->toBe(3)->and($import->valid_rows)->toBe(3)->and($import->rejected_rows)->toBe(0);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('requiredColumns', StudentImportColumns::required())
        ->where('rows.data.0.data', ['last_name' => 'RANDRIA', 'first_names' => 'Test Un', 'repeat_code' => 'N', 'level' => 'L1', 'mention' => 'Lettres modernes', 'program' => 'Tronc Commun'])
        ->missing('rows.data.0.data.cin')->missing('rows.data.0.data.phone'));

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors()->assertRedirect();
    expect($import->fresh()->status)->toBe(ImportStatus::Completed)->and($import->fresh()->created_rows)->toBe(3)->and($import->fresh()->declared_rows)->toBe(3);

    $first = Student::query()->where('last_name', 'RANDRIA')->sole();
    expect($first->cin)->toBe('413012049888')->and($first->hasStandardCin())->toBeTrue()->and($first->phone)->toBe('0327407096')
        ->and($first->birth_date->format('Y-m-d'))->toBe('2003-05-12')->and($first->cin_issued_at->format('Y-m-d'))->toBe('2021-08-02')
        ->and($first->mother_name)->toBe('RASOA Exemple')->and($first->nationality)->toBe('Malagasy')->and($first->bac_year)->toBe(2022)->and($first->bac_series)->toBe('A2');
    $record = $first->studentRecords()->sole();
    expect($record->repeat_code)->toBe(RepeatCode::New)->and($record->scholarship_request_reference)->toBe('DB-0001')
        ->and($record->scholarship_requested_at->format('Y-m-d'))->toBe('2026-10-05')->and($record->program_id)->toBe($this->lettersCommon->id)
        ->and($record->level_id)->toBe($this->level->id)->and($record->establishment_id)->toBe($this->establishment->id);

    $second = Student::query()->where('last_name', 'RAKOTO')->sole();
    expect($second->cin)->toBe('RECU2024-17')->and($second->hasStandardCin())->toBeFalse()->and($second->phone)->toBe('0340000002')
        ->and($second->birth_date->format('Y-m-d'))->toBe('2002-01-07')->and($second->cin_issued_at)->toBeNull();
    $record = $second->studentRecords()->sole();
    expect($record->repeat_code)->toBe(RepeatCode::Repeating)->and($record->program_id)->toBe($this->historyCommon->id)
        ->and($record->scholarship_requested_at->format('Y-m-d'))->toBe('2026-10-05');

    $third = Student::query()->where('last_name', 'RABE')->sole()->studentRecords()->sole();
    expect($third->repeat_code)->toBe(RepeatCode::TripleOrMore)->and($third->scholarship_request_reference)->toBeNull()->and($third->scholarship_requested_at)->toBeNull();
});

it('rejects descriptive columns that disagree with the referentials', function (array $overrides, string $expected) {
    $import = analyzeTemplate($this, [templateRow($overrides)]);
    expect($import->rejected_rows)->toBe(1)->and(rowErrorCodes($import))->toContain($expected);
})->with([
    'other establishment' => [['Ecole / Institut' => 'Ecole Autre (AUTRE)'], 'establishment:other_establishment'],
    'other establishment name' => [['Ecole / Institut' => 'Institut Exemple'], 'establishment:other_establishment'],
    'domain of another mention' => [['Domaine' => '(STS) Sciences, Technologies et Santé'], 'domain:domain_mismatch'],
    'unknown domain' => [['Domaine' => 'Domaine inconnu'], 'domain:domain_mismatch'],
    'other training type' => [['Type de formation' => 'Professionnalisant'], 'training_type:training_type_mismatch'],
    'unknown training type' => [['Type de formation' => 'À distance'], 'training_type:training_type_mismatch'],
    'other sector' => [['Institution' => 'Privée'], 'sector:sector_mismatch'],
    'unknown sector' => [['Institution' => 'Mixte'], 'sector:sector_mismatch'],
    'mention without program' => [['Parcours' => null], 'program:missing_program'],
    'unknown repeat code' => [['CODE DE REDOUBLEMENT' => 'Q (Quadruplant)'], 'repeat_code:invalid_repeat_code'],
    'missing sex' => [['SEXE' => null], 'sex:invalid_sex'],
    'unlisted nationality' => [['NATIONALITÉ' => 'Martienne'], 'nationality:invalid_nationality'],
    'unlisted BAC series' => [['SÉRIE DU BACC' => 'Z9'], 'bac_series:invalid_bac_series'],
]);

it('rejects an ambiguous program name when no mention is given', function () {
    $import = analyzeTemplate($this, [templateRow(['Mention' => null, 'Domaine' => null])]);
    expect(rowErrorCodes($import))->toContain('program:ambiguous');
});

it('creates the missing mention and program in the import establishment, with the domain and training type of the file', function () {
    $import = analyzeTemplate($this, [
        templateRow(['Mention' => 'Droit', 'Parcours' => 'Tronc Commun', 'Type de formation' => 'Professionnalisant']),
        templateRow(['NOM' => 'RABE', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'rabe@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002', 'Mention' => ' droit ', 'Parcours' => 'Droit Privé', 'Type de formation' => 'Professionnalisant']),
        // Existing mention, missing program inside it.
        templateRow(['NOM' => 'RAVELO', 'CIN' => 413012049890, 'NUMERO TELEPHONE' => 327407098, 'ADRESSE MAIL' => 'ravelo@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0003', 'Parcours' => 'Linguistique']),
    ]);
    expect($import->valid_rows)->toBe(3)
        ->and($import->reference_changes)->toBe(['levels' => [], 'domains' => [], 'mentions' => ['Droit'], 'programs' => ['Droit › Tronc Commun', 'droit › Droit Privé', 'Lettres modernes › Linguistique'], 'created' => false]);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.notices', [
        'Nouvelle mention « Droit » : elle sera créée dans l’établissement.', 'Nouveau parcours « Tronc Commun » : il sera créé dans l’établissement.',
    ]));
    expect(Mention::query()->where('name', 'Droit')->exists())->toBeFalse();
    $audited = Activity::query()->where('log_name', 'references')->where('event', 'created')->count();

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $law = Mention::query()->where('establishment_id', $this->establishment->id)->where('name', 'Droit')->sole();
    expect($law->domain_id)->toBe($this->alsh->id)
        ->and($law->programs()->orderBy('name')->pluck('name')->all())->toBe(['Droit Privé', 'Tronc Commun'])
        ->and($law->programs()->first()->training_type)->toBe(TrainingType::Professional)
        ->and($this->letters->programs()->where('name', 'Linguistique')->exists())->toBeTrue()
        ->and(StudentRecord::query()->whereNull('program_id')->exists())->toBeFalse()
        ->and($import->fresh()->reference_changes['created'])->toBeTrue()
        ->and($import->fresh()->reference_changes['mentions'])->toBe(['Droit'])
        ->and(Activity::query()->where('log_name', 'references')->where('event', 'created')->count() - $audited)->toBe(4);
});

it('creates a domain given as "(CODE) Name" with the new mention, and rejects an unreadable unknown domain', function () {
    $import = analyzeTemplate($this, [
        templateRow(['Mention' => 'Économie', 'Parcours' => 'Gestion', 'Domaine' => '(DEG) Droit, Économie et Gestion']),
        templateRow(['NOM' => 'RABE', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'rabe@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002', 'Mention' => 'Sociologie', 'Parcours' => 'Général', 'Domaine' => 'Sciences sociales']),
    ]);
    expect($import->valid_rows)->toBe(1)->and(rowErrorCodes($import, 1))->toContain('domain:unknown_domain');

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $domain = Domain::query()->where('code', 'DEG')->sole();
    expect($domain->name)->toBe('Droit, Économie et Gestion')
        ->and(Mention::query()->where('name', 'Économie')->sole()->domain_id)->toBe($domain->id)
        ->and(Mention::query()->where('name', 'Sociologie')->exists())->toBeFalse();
});

it('never duplicates an inactive mention or program', function () {
    $this->letters->update(['is_active' => false]);
    $import = analyzeTemplate($this, [templateRow()]);
    expect(rowErrorCodes($import))->toContain('mention:inactive');
    $this->letters->update(['is_active' => true]);
    $this->lettersCommon->update(['is_active' => false]);
    expect(rowErrorCodes(analyzeTemplate($this, [templateRow()])))->toContain('program:inactive');
});

it('accepts the referential checks once the referentials leave them open', function () {
    $this->establishment->update(['sector' => null]);
    $this->letters->update(['domain_id' => null]);
    $this->lettersCommon->update(['training_type' => null]);
    $import = analyzeTemplate($this, [templateRow(['Institution' => 'Privée', 'Domaine' => '(STS) Sciences, Technologies et Santé', 'Type de formation' => 'Professionnel'])]);
    expect($import->valid_rows)->toBe(1);
});

it('rejects a phone or email already owned by another student', function (string $header, mixed $value, string $field, array $owner) {
    Student::factory()->create(['last_name' => 'AUTRE', ...$owner]);
    $import = analyzeTemplate($this, [templateRow([$header => $value])]);
    expect($import->rejected_rows)->toBe(1)->and(rowErrorCodes($import))->toContain($field.':already_used');
})->with([
    'phone from a numeric cell' => ['NUMERO TELEPHONE', 327407096, 'phone', ['phone' => '0327407096']],
    'email in another case' => ['ADRESSE MAIL', 'TEST.UN@example.test', 'email', ['email' => 'test.un@example.test']],
]);

it('matches an existing identity by its standard CIN from a numeric cell', function () {
    $student = Student::factory()->create(['last_name' => 'RANDRIA', 'first_names' => 'Test Un', 'sex' => 'M', 'birth_date' => '2003-05-12', 'cin' => '413012049888', 'phone' => null, 'email' => null, 'mother_name' => null, 'nationality' => 'Malagasy']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => $this->establishment->id]);
    $import = analyzeTemplate($this, [templateRow()]);
    expect($import->valid_rows)->toBe(1)->and($import->rows()->sole()->matched_student_id)->toBe($student->id);
});

it('flags the same identity twice as a duplicate and another identity reusing a request reference as rejected', function () {
    $import = analyzeTemplate($this, [
        templateRow(),
        templateRow(['PRENOMS' => 'test un', 'NOM' => ' randria ']),
        templateRow(['NOM' => 'RAKOTO', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'test.deux@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'db-0001']),
        templateRow(['NOM' => 'RABE', 'CIN' => 413012049888, 'NUMERO TELEPHONE' => 327407098, 'ADRESSE MAIL' => 'test.trois@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0003']),
    ]);
    $rows = $import->rows()->orderBy('row_number')->get();
    expect($rows->pluck('status')->all())->toBe([RowStatus::Valid, RowStatus::Duplicate, RowStatus::Rejected, RowStatus::Rejected])
        ->and(rowErrorCodes($import, 2))->toBe(['scholarship_request_reference:file_unique'])
        ->and(rowErrorCodes($import, 3))->toBe(['cin:file_unique']);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(Student::count())->toBe(1)->and(StudentRecord::count())->toBe(1)->and($import->fresh()->skipped_rows)->toBe(3);
});

it('rejects a request reference already given in the establishment for the year', function () {
    StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'scholarship_request_reference' => 'DB-0001']);
    $import = analyzeTemplate($this, [templateRow()]);
    expect(rowErrorCodes($import))->toBe(['scholarship_request_reference:reference_used']);
});

it('creates a missing standard LMD level, never an unknown one', function () {
    $import = analyzeTemplate($this, [
        templateRow(['Niveau' => 'L3']),
        templateRow(['NOM' => 'RABE', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'rabe@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002', 'Niveau' => 'Licence 3']),
        templateRow(['NOM' => 'RAVELO', 'CIN' => 413012049890, 'NUMERO TELEPHONE' => 327407098, 'ADRESSE MAIL' => 'ravelo@example.test', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0003', 'Niveau' => 'Doctorat X']),
    ]);
    expect($import->valid_rows)->toBe(2)->and($import->reference_changes['levels'])->toBe(['L3'])->and(rowErrorCodes($import, 2))->toContain('level:unknown_or_ambiguous');

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $level = Level::query()->where('code', 'L3')->sole();
    expect($level->name)->toBe('Licence 3')->and($level->sort_order)->toBe(3)
        ->and(StudentRecord::query()->where('level_id', $level->id)->count())->toBe(2)
        ->and($import->fresh()->reference_changes['levels'])->toBe(['L3']);
});

it('summarises why rows cannot be imported, with the referential values of the file, and announces creations only on importable rows', function () {
    $import = analyzeTemplate($this, [
        templateRow(['Ecole / Institut' => 'Ecole de Droit (EDSP)', 'Mention' => 'Droit']),
        templateRow(['NOM' => 'RABE', 'CIN' => 413012049889, 'NUMERO TELEPHONE' => 327407097, 'ADRESSE MAIL' => 'pas un email', 'REFERENCE DEMANDE DE BOURSE' => 'DB-0002', 'Ecole / Institut' => 'Ecole de Droit (EDSP)']),
    ]);
    expect($import->rejected_rows)->toBe(2)
        ->and($import->issue_summary[0])->toMatchArray(['field' => 'establishment', 'code' => 'other_establishment', 'severity' => 'error', 'rows' => 2, 'values' => ['Ecole de Droit (EDSP)']])
        ->and(collect($import->issue_summary)->firstWhere('field', 'email'))->toMatchArray(['rows' => 1, 'values' => []])
        ->and($import->reference_changes['mentions'])->toBe([]);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.notices', [])->where('import.issue_summary.0.rows', 2));
});

it('recognises the establishment of the import however the file writes it', function (string $written, bool $same) {
    $this->establishment->update(['code' => 'EDSP', 'name' => 'Ecole de Droit et Science Politique (Antsohihy)']);
    $import = analyzeTemplate($this, [templateRow(['Ecole / Institut' => $written])]);
    expect(in_array('establishment:other_establishment', rowErrorCodes($import), true))->toBe(! $same);
})->with([
    'official file' => ['Ecole de Droit et Science Politique Antsohihy (EDSP Antsohihy)', true],
    'exact name' => ['Ecole de Droit et Science Politique (Antsohihy)', true],
    'accents and case' => ['ÉCOLE DE DROIT ET SCIENCE POLITIQUE ANTSOHIHY', true],
    'acronym' => ['EDSP', true],
    'name and acronym' => ['Ecole de Droit et Science Politique (EDSP)', true],
    'another city' => ['Ecole de Droit et Science Politique Mahajanga (EDSP Mahajanga)', false],
    'another school' => ['Faculté des Sciences (FST)', false],
    'part of the name' => ['Ecole de Droit', false],
]);
