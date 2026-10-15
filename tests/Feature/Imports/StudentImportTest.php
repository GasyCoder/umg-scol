<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Actions\Imports\CommitStudentImport;
use App\Enums\StudentImportRowStatus as RowStatus;
use App\Enums\StudentImportStatus as ImportStatus;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentImportColumns;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->a = Establishment::factory()->create();
    $this->b = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create();
    $this->oldYear = AcademicYear::factory()->create();
    $this->level = Level::factory()->create(['code' => 'L1']);
    $this->program = Program::factory()->create(['establishment_id' => $this->a->id, 'code' => 'INFO']);
    $this->actor = User::factory()->create();
    $this->actor->forceFill(['establishment_id' => $this->a->id])->save();
    $this->actor->assignRole('establishment');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->actingAs($this->actor);
    $this->data = ['last_name' => 'Nom', 'first_names' => 'Prénom', 'sex' => 'M', 'birth_date' => '2001-01-01', 'nationality' => 'Malagasy', 'repeat_code' => 'N', 'level' => 'L1', 'program' => 'INFO'];
});

/** @param list<array<string,mixed>> $rows */
function uploadStudentCsv(TestCase $test, array $rows): StudentImport
{
    $fields = array_keys(StudentImportColumns::labels());
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, $fields, ';', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn ($field) => $row[$field] ?? '', $fields), ';', '"', '');
    }
    rewind($stream);
    $file = UploadedFile::fake()->createWithContent('etudiants.csv', stream_get_contents($stream));
    fclose($stream);
    $test->post('/imports', ['file' => $file, 'academic_year_id' => $test->year->id])->assertSessionHasNoErrors()->assertRedirect();

    return StudentImport::query()->latest('id')->firstOrFail();
}
function analyzeStudentCsv(TestCase $test, array $rows): StudentImport
{
    $import = uploadStudentCsv($test, $rows);
    app(AnalyzeStudentImport::class)->handle($test->actor, $import, $import->mapping);

    return $import->fresh();
}
function existingImportStudent(TestCase $test, array $attributes = []): Student
{
    $student = Student::factory()->create(['last_name' => 'Nom', 'first_names' => 'Prénom', 'sex' => 'M', 'birth_date' => '2001-01-01', 'nationality' => 'Malagasy', 'email' => null, 'cin' => null, ...$attributes]);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $test->oldYear->id, 'establishment_id' => $test->a->id, 'level_id' => $test->level->id, 'program_id' => $test->program->id]);

    return $student;
}

it('accepts CSV and privately stages without creating identities', function () {
    $import = uploadStudentCsv($this, [$this->data]);
    expect($import->status)->toBe(ImportStatus::Uploaded)->and($import->total_rows)->toBe(1)->and(Student::count())->toBe(0)->and(StudentRecord::count())->toBe(0)->and($import->disk)->toBe('student_imports')->and($import->stored_path)->not->toContain('etudiants.csv');
    Storage::disk('student_imports')->assertExists($import->stored_path);
    expect(config('filesystems.disks.student_imports.root'))->toContain('app/private')->and(config('filesystems.disks.student_imports.serve'))->toBeFalse();
});
it('accepts a genuine XLSX and converts numeric Excel dates', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['Nom', 'Niveau', 'Date de naissance'], ['Nom', 'L1', 37000]]);
    $path = tempnam(sys_get_temp_dir(), 'import-test-');
    (new Xlsx($book))->save($path);
    try {
        $this->post('/imports', ['file' => new UploadedFile($path, 'etudiants.xlsx', null, null, true), 'academic_year_id' => $this->year->id])->assertSessionHasNoErrors()->assertRedirect();
        $import = StudentImport::sole();
        app(AnalyzeStudentImport::class)->handle($this->actor, $import, $import->mapping);
        expect($import->rows()->sole()->normalized_data['birth_date'])->toBe('2001-04-19');
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
});
it('rejects forbidden files and spoofed extensions', function (string $filename, string $content) {
    $this->post('/imports', ['file' => UploadedFile::fake()->createWithContent($filename, $content), 'academic_year_id' => $this->year->id])->assertSessionHasErrors('file');
    expect(StudentImport::count())->toBe(0);
})->with([['script.php', '<?php echo 1;'], ['legacy.xls', 'name,level'], ['fake.xlsx', 'not an Excel archive'], ['fake.csv', '%PDF-1.7 invalid binary']]);
it('rejects oversized uploads', function () {
    config(['student_imports.max_kilobytes' => 1]);
    $this->post('/imports', ['file' => UploadedFile::fake()->create('rows.csv', 2, 'text/csv'), 'academic_year_id' => $this->year->id])->assertSessionHasErrors('file');
});
it('rejects inactive references on upload', function (string $reference) {
    $this->{$reference}->update(['is_active' => false]);
    $response = $this->post('/imports', ['file' => UploadedFile::fake()->createWithContent('rows.csv', "last_name,level\nNom,L1"), 'academic_year_id' => $this->year->id]);
    if ($reference === 'a') {
        $response->assertForbidden();
    } else {
        $response->assertSessionHasErrors('academic_year_id');
    }
})->with(['a', 'year']);
it('rejects foreign and malformed establishment payloads', function (mixed $value) {
    $this->post('/imports', ['file' => UploadedFile::fake()->createWithContent('rows.csv', "last_name,level\nNom,L1"), 'academic_year_id' => $this->year->id, 'establishment_id' => $value === 'foreign' ? $this->b->id : $value])->assertForbidden();
})->with([['foreign'], [null], [[]], [999999]]);
it('isolates every import endpoint from a foreign establishment', function (string $endpoint, string $method) {
    $import = uploadStudentCsv($this, [$this->data]);
    $import->update(['establishment_id' => $this->b->id]);
    $this->{$method}('/imports/'.$import->uuid.$endpoint)->assertForbidden();
})->with([['', 'get'], ['/analyze', 'post'], ['/commit', 'post'], ['/errors', 'get']]);
it('blocks central and student roles even if granted import permissions', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    $user->givePermissionTo(['imports.view', 'imports.create', 'imports.commit', 'imports.export_errors']);
    $this->actingAs($user)->get('/imports')->assertForbidden();
    $this->get('/imports/create')->assertForbidden();
})->with(['student', 'central_scholarship_office']);
it('allows superadmin to select an establishment', function () {
    $this->actingAs($this->admin)->post('/imports', ['file' => UploadedFile::fake()->createWithContent('rows.csv', "last_name,level\nNom,L1"), 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id])->assertSessionHasNoErrors()->assertRedirect();
    expect(StudentImport::sole()->establishment_id)->toBe($this->b->id);
});
it('maps canonical fields and French aliases deterministically', function () {
    expect(StudentImportColumns::suggest(['NOM', 'Prénoms', 'Sexe', 'N° matricule', 'CIN', 'Niveau', 'Code redoublement']))->toBe(['last_name' => 0, 'first_names' => 1, 'sex' => 2, 'cin' => 4, 'level' => 5, 'repeat_code' => 6]);
    expect(StudentImportColumns::suggest(array_keys(StudentImportColumns::labels())))->toHaveCount(24)
        ->and(StudentImportColumns::suggest(array_values(StudentImportColumns::labels())))->toHaveCount(24);
});
it('rejects incomplete, duplicated, unknown and out of range mappings', function (string $case) {
    $import = uploadStudentCsv($this, [$this->data]);
    $mapping = $import->mapping;
    match ($case) {
        'missing sex' => $mapping = array_diff_key($mapping, ['sex' => true]),
        'missing repeat code' => $mapping = array_diff_key($mapping, ['repeat_code' => true]),
        'duplicated' => $mapping['level'] = $mapping['last_name'],
        'out of range' => $mapping['level'] = 999,
        'unknown' => $mapping['student_number'] = 998,
    };
    $this->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $mapping])->assertSessionHasErrors();
    expect($import->fresh()->status)->toBe(ImportStatus::Uploaded);
})->with(['missing sex', 'missing repeat code', 'duplicated', 'out of range', 'unknown']);
it('permits a corrected mapping before analysis', function () {
    $import = uploadStudentCsv($this, [$this->data]);
    $this->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $import->mapping])->assertSessionHasNoErrors()->assertRedirect();
    expect($import->fresh()->status)->toBe(ImportStatus::Ready)->and(Student::count())->toBe(0);
});
it('reuses student normalization and preserves identifiers', function () {
    $data = StudentImportColumns::normalize(
        [' EMAIL@EXAMPLE.TEST ', ' 034 (12)-345 67 ', ' 123 456 ', 'n (Nouveau)', '   ', '01/02/2001', 413012049888.0, 327407096.0, ' féminin ', 'MALAGASY', '  REF  12 ', 'tech. génie civil'],
        ['email' => 0, 'phone' => 1, 'cin' => 2, 'repeat_code' => 3, 'first_names' => 4, 'birth_date' => 5, 'cin_issued_place' => 6, 'address' => 7, 'sex' => 8, 'nationality' => 9, 'scholarship_request_reference' => 10, 'bac_series' => 11],
    );
    expect($data)->toBe(['email' => 'email@example.test', 'phone' => '0341234567', 'cin' => '123456', 'repeat_code' => 'N', 'first_names' => null, 'birth_date' => '2001-02-01',
        'cin_issued_place' => '413012049888', 'address' => '327407096', 'sex' => 'F', 'nationality' => 'Malagasy', 'scholarship_request_reference' => 'REF 12', 'bac_series' => 'Tech. Génie civil']);
    expect(StudentImportColumns::normalize([413012049888.0, 327407096.0], ['cin' => 0, 'phone' => 1]))->toBe(['cin' => '413012049888', 'phone' => '0327407096']);
});
it('rejects invalid row fields with structured errors', function (string $field, mixed $value) {
    $import = analyzeStudentCsv($this, [[...$this->data, $field => $value]]);
    $row = $import->rows()->sole();
    expect($row->status)->toBe(RowStatus::Rejected)->and($row->errors[0])->toHaveKeys(['field', 'code', 'message']);
})->with([['last_name', ''], ['email', 'invalid'], ['level', 'unknown'], ['birth_date', '31/02/2001'], ['birth_date', '2999-01-01'], ['birth_date', ''], ['phone', 'bad'], ['bac_year', 1800], ['cin', str_repeat('1', 101)], ['last_name', '=1+1'],
    ['sex', ''], ['sex', 'X'], ['nationality', ''], ['nationality', 'Martienne'], ['repeat_code', ''], ['repeat_code', 'Q (Quadruplant)'], ['bac_series', 'Z9'], ['scholarship_requested_at', '2026-01-01']]);
it('rejects foreign inactive or ambiguous programs', function (string $case) {
    if ($case === 'foreign') {
        $this->program->update(['establishment_id' => $this->b->id]);
    } elseif ($case === 'inactive') {
        $this->program->update(['is_active' => false]);
    } else {
        $this->program->update(['code' => null, 'name' => 'Parcours']);
        Program::factory()->create(['establishment_id' => $this->a->id, 'code' => null, 'name' => 'Parcours']);
        $this->data['program'] = 'Parcours';
    }
    $import = analyzeStudentCsv($this, [$this->data]);
    expect($import->rejected_rows)->toBe(1);
})->with(['foreign', 'inactive', 'ambiguous']);
it('rejects CIN issued before birth', function () {
    $import = analyzeStudentCsv($this, [[...$this->data, 'birth_date' => '2001-01-01', 'cin_issued_at' => '2000-01-01']]);
    expect($import->rejected_rows)->toBe(1);
});
it('detects the same identity twice across chunk boundaries as a duplicate', function (array $identifiers) {
    config(['student_imports.chunk_size' => 1]);
    $row = [...$this->data, ...$identifiers];
    $import = analyzeStudentCsv($this, [$row, $row]);
    expect($import->valid_rows)->toBe(1)->and($import->duplicate_rows)->toBe(1)
        ->and($import->rows()->where('status', 'duplicate')->sole()->warnings[0]['code'])->toBe('file_duplicate');
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(Student::count())->toBe(1)->and(StudentRecord::count())->toBe(1);
})->with([
    'names and birth date only' => [[]],
    'standard CIN' => [['cin' => '101234567890']],
    'phone' => [['phone' => '0341234567']],
    'email' => [['email' => 'same@example.test']],
    'request reference' => [['scholarship_request_reference' => 'REF-1']],
]);
it('rejects another identity of the file reusing a unique value', function (string $field, string $value) {
    config(['student_imports.chunk_size' => 1]);
    $first = [...$this->data, $field => $value];
    $import = analyzeStudentCsv($this, [$first, [...$first, 'last_name' => 'Autre']]);
    expect($import->valid_rows)->toBe(1)->and($import->rejected_rows)->toBe(1);
    expect($import->rows()->where('status', 'rejected')->sole()->errors)->toContainEqual(['field' => $field, 'code' => 'file_unique', 'message' => 'Valeur déjà utilisée par une autre ligne du fichier ('.StudentImportColumns::labels()[$field].').']);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(Student::count())->toBe(1)->and(StudentRecord::count())->toBe(1);
})->with([['cin', '101234567890'], ['phone', '0341234567'], ['email', 'shared@example.test'], ['scholarship_request_reference', 'REF-1']]);
it('accepts a non-standard CIN shared by two identities of the file', function () {
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => 'RECU 42'], [...$this->data, 'last_name' => 'Autre', 'cin' => 'RECU 42']]);
    expect($import->valid_rows)->toBe(2);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(Student::where('cin', 'RECU42')->count())->toBe(2);
});
it('detects exact name and date duplicates without strong identifiers', function () {
    $import = analyzeStudentCsv($this, [$this->data, [...$this->data, 'last_name' => ' NOM ', 'first_names' => 'prénom']]);
    expect($import->valid_rows)->toBe(1)->and($import->duplicate_rows)->toBe(1);
});
it('matches a single existing identity and fills empty fields only', function (string $key) {
    // By standard CIN (the official birth date is still empty) or by names and birth date.
    $student = existingImportStudent($this, $key === 'cin' ? ['cin' => '101234567890', 'birth_date' => null, 'mother_name' => 'Mère officielle'] : ['mother_name' => 'Mère officielle']);
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => '101234567890', 'email' => ' New@Example.test ']]);
    expect($import->valid_rows)->toBe(1)->and($import->rows()->sole()->matched_student_id)->toBe($student->id);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    $student->refresh();
    expect(Student::count())->toBe(1)->and($student->email)->toBe('new@example.test')->and($student->cin)->toBe('101234567890')
        ->and($student->birth_date->format('Y-m-d'))->toBe('2001-01-01')->and($student->mother_name)->toBe('Mère officielle')
        ->and(StudentRecord::count())->toBe(2)->and($import->fresh()->updated_rows)->toBe(1)->and($import->fresh()->created_rows)->toBe(0);
})->with(['cin', 'identity']);
it('matches exact names and birth date when no identifier exists', function () {
    $student = existingImportStudent($this);
    $import = analyzeStudentCsv($this, [[...$this->data, 'last_name' => ' Nom ']]);
    expect($import->rows()->sole()->matched_student_id)->toBe($student->id);
});
it('does not match an identity by a non-standard CIN', function () {
    existingImportStudent($this, ['last_name' => 'Autre', 'cin' => 'RECU42']);
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => 'RECU 42']]);
    expect($import->valid_rows)->toBe(1)->and($import->rows()->sole()->matched_student_id)->toBeNull();
});
it('rejects a phone or email owned by another existing student', function (string $field, string $value) {
    existingImportStudent($this, ['last_name' => 'Autre', $field => $value]);
    $import = analyzeStudentCsv($this, [[...$this->data, $field => $value]]);
    expect($import->rejected_rows)->toBe(1)->and(collect($import->rows()->sole()->errors)->firstWhere('field', $field)['code'])->toBe('already_used');
})->with([['phone', '0341234567'], ['email', 'owned@example.test']]);
it('rejects a request reference already given to another student of the establishment and year', function () {
    StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'scholarship_request_reference' => 'REF-9']);
    $import = analyzeStudentCsv($this, [[...$this->data, 'scholarship_request_reference' => 'ref-9']]);
    expect($import->rejected_rows)->toBe(1)->and($import->rows()->sole()->errors[0]['code'])->toBe('reference_used');
    StudentRecord::query()->update(['establishment_id' => $this->b->id]);
    expect(analyzeStudentCsv($this, [[...$this->data, 'scholarship_request_reference' => 'REF-9']])->valid_rows)->toBe(1);
});
it('never merges multiple possible identities', function () {
    existingImportStudent($this);
    existingImportStudent($this, ['last_name' => 'Autre', 'cin' => '101234567890']);
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => '101234567890']]);
    expect($import->warning_rows)->toBe(1)->and($import->rows()->sole()->matched_student_id)->toBeNull();
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(Student::count())->toBe(2)->and($import->fresh()->declared_rows)->toBe(0);
});
it('never overwrites nonempty official or contact data', function (string $field, string $old, string $new) {
    // Matched by its standard CIN, except when the CIN itself differs (then by names and birth date).
    $student = existingImportStudent($this, ['cin' => '101234567890', $field => $old]);
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => '101234567890', $field => $new]]);
    expect($import->warning_rows)->toBe(1)->and(collect($import->rows()->sole()->warnings)->pluck('field')->all())->toContain($field);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect((string) ($student->fresh()->getAttribute($field)?->value ?? $student->fresh()->getAttribute($field)))->toBe($old);
})->with([['last_name', 'Nom', 'Autre'], ['cin', '123', '456'], ['sex', 'F', 'M'], ['nationality', 'Comorien', 'Malagasy'], ['email', 'old@example.test', 'new@example.test'], ['phone', '0341234567', '0321234567'], ['address', 'Ancienne adresse', 'Nouvelle adresse']]);
it('skips an identical annual record', function () {
    $student = existingImportStudent($this);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id]);
    $import = analyzeStudentCsv($this, [$this->data]);
    expect($import->duplicate_rows)->toBe(1);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(StudentRecord::count())->toBe(2)->and($import->fresh()->skipped_rows)->toBe(1);
});
it('warns on a contradictory annual record', function (array $attributes) {
    $student = existingImportStudent($this);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, ...$attributes]);
    $import = analyzeStudentCsv($this, [$this->data]);
    expect($import->warning_rows)->toBe(1)->and($import->rows()->sole()->warnings[0]['code'])->toBe('record_conflict');
})->with([
    'level' => [['level_id' => fn () => Level::factory()->create()->id, 'program_id' => null]],
    'repeat code' => [['repeat_code' => 'R']],
    'request reference' => [['scholarship_request_reference' => 'REF-OLD']],
]);
it('does not expose foreign identities or automatically transfer them', function () {
    $student = existingImportStudent($this);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id]);
    $import = analyzeStudentCsv($this, [$this->data]);
    expect($import->warning_rows)->toBe(1)->and($import->rows()->sole()->matched_student_id)->toBeNull();
    $this->actingAs($this->admin);
    app(AnalyzeStudentImport::class)->handle($this->admin, $import, $import->mapping);
    expect($import->rows()->sole()->warnings)->toContainEqual(['field' => 'record', 'code' => 'foreign_active_record', 'message' => 'Une déclaration active existe dans un autre établissement. Aucun transfert automatique.']);
});
it('commits only valid rows and ignores warnings rejected rows and duplicates', function () {
    existingImportStudent($this, ['last_name' => 'Ancien', 'cin' => '101234567890']);
    $import = analyzeStudentCsv($this, [$this->data, $this->data, [...$this->data, 'email' => 'bad'], [...$this->data, 'cin' => '101234567890', 'last_name' => 'Conflit']]);
    expect($import->valid_rows)->toBe(1)->and($import->duplicate_rows)->toBe(1)->and($import->warning_rows)->toBe(1)->and($import->rejected_rows)->toBe(1);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors()->assertRedirect();
    expect(Student::count())->toBe(2)->and(StudentRecord::count())->toBe(2)->and($import->fresh()->status)->toBe(ImportStatus::Completed)->and($import->fresh()->created_rows)->toBe(1)->and($import->fresh()->declared_rows)->toBe(1)->and($import->fresh()->skipped_rows)->toBe(3);
});
it('makes repeat commits a no-op and disallows reanalysis after completion', function () {
    $import = analyzeStudentCsv($this, [$this->data]);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $this->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $import->mapping])->assertSessionHasErrors('mapping');
    expect(Student::count())->toBe(1)->and(StudentRecord::count())->toBe(1)->and(Activity::query()->where('log_name', 'student_imports')->where('event', 'committed')->count())->toBe(1);
});
it('requires analysis before commit', function () {
    $import = uploadStudentCsv($this, [$this->data]);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasErrors('import');
    expect(Student::count())->toBe(0);
});
it('rechecks database conflicts introduced after preview', function () {
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => '101234567890']]);
    existingImportStudent($this, ['last_name' => 'Changed', 'cin' => '101234567890']);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(Student::count())->toBe(1)->and($import->fresh()->warning_rows)->toBe(1)->and($import->fresh()->skipped_rows)->toBe(1);
});
it('rechecks references at confirmation', function () {
    $import = analyzeStudentCsv($this, [$this->data]);
    $this->level->update(['is_active' => false]);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(Student::count())->toBe(0)->and($import->fresh()->rejected_rows)->toBe(1);
});
it('rolls back students records staging resolutions and audit on a critical error', function () {
    $import = analyzeStudentCsv($this, [$this->data, [...$this->data, 'last_name' => 'Deuxième']]);
    $count = 0;
    StudentRecord::creating(function () use (&$count) {
        if (++$count === 2) {
            throw new RuntimeException('simulated failure');
        }
    });
    try {
        $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasErrors('import');
    } finally {
        StudentRecord::flushEventListeners();
        StudentRecord::clearBootedModels();
    }
    expect(Student::count())->toBe(0)->and(StudentRecord::count())->toBe(0)->and($import->fresh()->status)->toBe(ImportStatus::Failed)->and($import->rows()->whereNotNull('resolution')->count())->toBe(0)->and(Activity::query()->where('log_name', 'students')->count())->toBe(0)->and(Activity::query()->where('event', 'committed')->count())->toBe(0);
});
it('audits upload analysis and commit without private data or filename', function () {
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => 'SECRET-CIN', 'email' => 'private@example.test', 'phone' => '0341234567', 'address' => 'SECRET-ADDRESS']]);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    $logs = Activity::query()->where('log_name', 'student_imports')->get();
    expect($logs->pluck('event')->all())->toBe(['uploaded', 'analyzed', 'committed'])->and($logs->first()->properties['valid_rows'])->toBe(0);
    $all = Activity::all()->toJson();
    foreach (['SECRET-CIN', 'private@example.test', '0341234567', 'SECRET-ADDRESS', 'etudiants.csv'] as $secret) {
        expect($all)->not->toContain($secret);
    }
    expect($logs->last()->causer_id)->toBe($this->actor->id)->and($logs->last()->properties['establishment_id'])->toBe($this->a->id);
});
it('paginates preview without sensitive data and filters status', function () {
    $rows = [];
    for ($i = 0; $i < 30; $i++) {
        $rows[] = [...$this->data, 'last_name' => 'Nom '.$i, 'cin' => 'SECRET-CIN-'.$i, 'phone' => '03400000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)];
    }
    $import = analyzeStudentCsv($this, $rows);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->component('imports/show')->has('rows.data', 25)->where('rows.total', 30)
        ->where('rows.data.0.data', ['last_name' => 'Nom 0', 'first_names' => 'Prénom', 'repeat_code' => 'N', 'level' => 'L1', 'mention' => null, 'program' => 'INFO'])
        ->missing('rows.data.0.data.cin')->missing('rows.data.0.data.phone')->missing('rows.data.0.data.birth_date')->missing('rows.data.0.raw_data')->missing('import.stored_path'));
    $this->get('/imports/'.$import->uuid.'?status=rejected')->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
});
it('loads import history with eager relations and scoped pagination', function () {
    $import = uploadStudentCsv($this, [$this->data]);
    $foreign = uploadStudentCsv($this, [$this->data]);
    $foreign->update(['establishment_id' => $this->b->id]);
    $this->get('/imports')->assertInertia(fn (Assert $page) => $page->has('imports.data', 1)->where('imports.data.0.id', $import->id)->missing('imports.data.0.stored_path'));
});
it('keeps preview and history relation queries bounded', function () {
    $rows = [];
    for ($i = 0; $i < 30; $i++) {
        $rows[] = [...$this->data, 'last_name' => 'Nom '.$i];
    }
    $import = analyzeStudentCsv($this, $rows);
    DB::enableQueryLog();
    $this->get('/imports/'.$import->uuid)->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    // The notification feed has its own query bounds. Keep the same strict limit for the
    // import preview and its relations: an N+1 on these 30 rows would add 30+ queries.
    $previewQueries = array_filter($queries, fn ($query) => preg_match('/\bfrom\s+[`"]?notifications[`"]?/i', $query['query']) !== 1);
    expect(count($previewQueries))->toBeLessThan(35);
});
it('chunks a multi-line file without persisting before confirmation', function () {
    config(['student_imports.chunk_size' => 100]);
    $rows = [];
    for ($i = 0; $i < 1001; $i++) {
        $rows[] = [...$this->data, 'last_name' => 'Nom '.$i];
    }
    $import = analyzeStudentCsv($this, $rows);
    expect($import->valid_rows)->toBe(1001)->and($import->rows()->count())->toBe(1001)->and(Student::count())->toBe(0);
    app(CommitStudentImport::class)->handle($this->actor, $import);
    expect(Student::count())->toBe(1001)->and(StudentRecord::count())->toBe(1001)->and($import->fresh()->declared_rows)->toBe(1001);
});
it('exports minimal error CSV and enforces the export permission', function () {
    $import = analyzeStudentCsv($this, [[...$this->data, 'email' => 'private-invalid', 'cin' => 'SECRET']]);
    $response = $this->get('/imports/'.$import->uuid.'/errors')->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toContain('invalid_email')->not->toContain('private-invalid')->not->toContain('SECRET');
    $this->actor->revokePermissionTo('imports.export_errors');
    $role = Role::findByName('establishment');
    $role->revokePermissionTo('imports.export_errors');
    $this->get('/imports/'.$import->uuid.'/errors')->assertForbidden();
});
it('purges private files and staging PII while retaining totals', function () {
    $import = analyzeStudentCsv($this, [$this->data]);
    $import->forceFill(['created_at' => now()->subDays(31)])->save();
    $this->artisan('students:purge-imports')->assertSuccessful();
    Storage::disk('student_imports')->assertMissing($import->stored_path);
    expect($import->fresh()->purged_at)->not->toBeNull()->and($import->fresh()->status)->toBe(ImportStatus::Cancelled)->and($import->fresh()->total_rows)->toBe(1)->and($import->rows()->sole()->raw_data)->toBe([])->and($import->rows()->sole()->normalized_data)->toBeNull();
});
it('rejects row limits without leaving a private file or partial staging', function () {
    config(['student_imports.max_rows' => 1]);
    $this->post('/imports', ['file' => UploadedFile::fake()->createWithContent('rows.csv', "last_name,level\nNom,L1\nAutre,L1"), 'academic_year_id' => $this->year->id])->assertSessionHasErrors('file');
    expect(StudentImport::count())->toBe(0)->and(Storage::disk('student_imports')->allFiles())->toBe([]);
});

it('preserves the 1904 Excel date calendar across upload and analysis requests', function () {
    $book = new Spreadsheet;
    $book->setExcelCalendar(1904);
    $book->getActiveSheet()->fromArray([['Nom', 'Niveau', 'Date de naissance'], ['Nom', 'L1', 37000]]);
    $path = tempnam(sys_get_temp_dir(), 'calendar-test-');
    (new Xlsx($book))->save($path);
    try {
        $this->post('/imports', ['file' => new UploadedFile($path, 'students.xlsx', null, null, true), 'academic_year_id' => $this->year->id])->assertSessionHasNoErrors();
        $import = StudentImport::sole();
        expect($import->excel_calendar)->toBe(1904);
        Date::setExcelCalendar(1900);
        app(AnalyzeStudentImport::class)->handle($this->actor, $import, $import->mapping);
        expect($import->rows()->sole()->normalized_data['birth_date'])->toBe('2005-04-20');
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
        Date::setExcelCalendar(1900);
    }
});

it('refuses a proposed date inconsistent with an existing official birth date', function () {
    existingImportStudent($this, ['cin' => '101234567890', 'birth_date' => '2001-01-01']);
    $import = analyzeStudentCsv($this, [[...$this->data, 'cin' => '101234567890', 'birth_date' => '1990-01-01', 'cin_issued_at' => '2000-01-01']]);
    expect($import->warning_rows)->toBe(1)->and(collect($import->rows()->sole()->warnings)->pluck('code')->all())->toContain('merged_before_birth', 'official_conflict');
});

it('refuses multi-sheet workbooks rather than silently ignoring data', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([['Nom', 'Niveau'], ['Nom', 'L1']]);
    $book->createSheet()->fromArray([['Nom', 'Niveau'], ['Autre', 'L1']]);
    $path = tempnam(sys_get_temp_dir(), 'sheets-test-');
    (new Xlsx($book))->save($path);
    try {
        $this->post('/imports', ['file' => new UploadedFile($path, 'students.xlsx', null, null, true), 'academic_year_id' => $this->year->id])->assertSessionHasErrors('file');
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
    expect(StudentImport::count())->toBe(0);
});

it('hides a foreign match identified by an admin before an establishment views the preview', function () {
    $student = Student::factory()->create(['last_name' => 'Nom', 'first_names' => 'Prénom', 'birth_date' => '2001-01-01']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->oldYear->id, 'establishment_id' => $this->b->id, 'status' => 'inactive']);
    $import = uploadStudentCsv($this, [$this->data]);
    app(AnalyzeStudentImport::class)->handle($this->admin, $import, $import->mapping);
    expect($import->rows()->sole()->matched_student_id)->toBe($student->id);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.matched_student_id', null));
});

it('always interprets CSV numeric dates using calendar 1900', function () {
    Date::setExcelCalendar(1904);
    try {
        $import = analyzeStudentCsv($this, [[...$this->data, 'birth_date' => 37000]]);
        expect($import->excel_calendar)->toBe(1900)->and($import->rows()->sole()->normalized_data['birth_date'])->toBe('2001-04-19');
    } finally {
        Date::setExcelCalendar(1900);
    }
});
it('offers a French import template that the column mapping recognises', function () {
    $csv = $this->actingAs($this->actor)->get('/imports/template')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('Nom;Prénoms;Sexe;"Date de naissance"')->not->toContain('Matricule');
    $headers = str_getcsv(trim(substr($csv, 3)), ';');
    expect(StudentImportColumns::suggest($headers))->toHaveCount(24)->toHaveKeys(StudentImportColumns::required());
});
