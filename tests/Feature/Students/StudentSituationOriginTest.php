<?php

use App\Actions\Imports\AnalyzeStudentImport;
use App\Actions\Scholarships\ReceiveScholarshipApplication;
use App\Enums\ImportKind;
use App\Enums\SituationSource;
use App\Enums\SituationVerification;
use App\Enums\StudentRecordStatus;
use App\Jobs\AnalyzeStudentImportJob;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Imports\ImportProgress;
use App\Support\StudentNormalization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
 * Scholarship holders (PNES base), non scholarship holders entered by the central office then
 * confirmed by the establishment list, and new bachelors (pre-registration). Fictitious people only.
 */
uses(TestCase::class, RefreshDatabase::class);

const PNES_HEADERS = ['Code Bourse', 'UIN', 'Numero de Compte', 'Nom', 'Prenom', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Code Redoublement'];
const LIST_HEADERS = ['Nom', 'Prenom', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Code Redoublement'];
const PREINSCRIPTION_HEADERS = ['Numero de dossier', 'Nom', 'Prenom', 'Sexe', 'Date de Naissance', 'Nom de la Mere', 'Numero CIN', 'Numero de Telephone', 'E-mail', 'Institution', 'Institution Code', 'Mention', 'Parcours', 'Niveau', 'Resultat'];

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('student_imports');
    $this->year = AcademicYear::factory()->create(['is_current' => true, 'is_active' => true, 'scholarship_max_bac_year' => 2025]);
    $this->facmed = Establishment::factory()->create(['code' => 'FACMED', 'name' => 'Faculté de Médecine']);
    $this->other = Establishment::factory()->create(['code' => 'ISSTM', 'name' => 'Institut Supérieur des Sciences']);
    $this->level = Level::factory()->create(['code' => 'L2', 'name' => 'Licence 2']);
    Level::factory()->create(['code' => 'L1', 'name' => 'Licence 1']);
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->staff = User::factory()->create(['establishment_id' => $this->facmed->id])->assignRole('establishment');
});

/** @param list<string> $headers @param list<list<mixed>> $rows */
function importFile(TestCase $test, User $actor, array $headers, array $rows, ImportKind $kind, ?int $establishment = null): StudentImport
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray([$headers, ...$rows]);
    $path = tempnam(sys_get_temp_dir(), 'kind').'.xlsx';
    (new Xlsx($book))->save($path);
    $test->actingAs($actor)->post('/imports', array_filter(['kind' => $kind->value, 'file' => new UploadedFile($path, 'liste.xlsx', null, null, true), 'establishment_id' => $establishment, 'academic_year_id' => $test->year->id], fn ($value) => $value !== null))->assertSessionHasNoErrors();
    $import = StudentImport::query()->latest('id')->firstOrFail();
    $test->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $import->mapping])->assertSessionHasNoErrors();

    return $import->refresh();
}

/** @return list<mixed> */
function pnesRow(string $code, string $name, string $cin, string $phone = '0341234567', string $email = 'x@example.test'): array
{
    return [$code, 'UIN'.str_replace('-', '', $code), '00012345678901', $name, 'Test', '12/10/2004', 'MERE Exemple', $cin, $phone, $email, 'Faculté de Médecine', 'FACMED', 'Médecine', 'Médecine', 'L2', 'N'];
}

it('restricts the central files to the central office and keeps establishments on their own list', function () {
    $this->actingAs($this->staff)->get('/imports/create')->assertInertia(fn (Assert $page) => $page->has('kinds', 1)->where('kinds.0.value', 'establishment'));
    $this->get('/imports/template?kind=pnes')->assertForbidden();
    $this->post('/imports', ['kind' => 'pnes', 'file' => UploadedFile::fake()->createWithContent('pnes.csv', "Nom\nX"), 'academic_year_id' => $this->year->id])->assertForbidden();
    $this->actingAs($this->central)->get('/imports/create')->assertInertia(fn (Assert $page) => $page->has('kinds', 2)->where('kinds.0.value', 'pnes')->where('kinds.1.value', 'preinscription'));
    $this->get('/imports/template?kind=pnes')->assertOk();
    $this->get('/imports/template?kind=establishment')->assertForbidden();
    // An establishment list is not visible to the central office without imports.view.
    $list = importFile($this, $this->staff, LIST_HEADERS, [['RABE', 'Hery', '05/04/2003', 'MERE', '501012345678', '0347654321', 'h@example.test', 'Faculté de Médecine', 'FACMED', 'Médecine', 'Médecine', 'L2', 'N']], ImportKind::Establishment, $this->facmed->id);
    $this->actingAs($this->central)->get('/imports/'.$list->uuid)->assertForbidden();
    $this->get('/imports')->assertInertia(fn (Assert $page) => $page->has('imports.data', 0));
});

it('lists the imports with their kind, filters them and preselects the kind to import', function () {
    importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678')], ImportKind::Pnes);
    $this->get('/imports')->assertInertia(fn (Assert $page) => $page->has('imports.data', 1)->where('imports.data.0.kind', 'pnes')
        ->where('kinds', [['value' => 'pnes', 'label' => 'Base PNES des boursiers', 'count' => 1, 'canCreate' => true], ['value' => 'preinscription', 'label' => 'Résultats de préinscription', 'count' => 0, 'canCreate' => true]]));
    $this->get('/imports?kind=preinscription')->assertInertia(fn (Assert $page) => $page->has('imports.data', 0)->where('filters.kind', 'preinscription'));
    $this->get('/imports/create?kind=preinscription')->assertInertia(fn (Assert $page) => $page->where('initialKind', 'preinscription'));
    $this->actingAs($this->staff)->get('/imports')->assertInertia(fn (Assert $page) => $page->has('kinds', 1)->where('kinds.0.value', 'establishment'));
});

it('imports the PNES base as scholarship holders and marks an existing situation', function () {
    $existing = StudentRecord::factory()->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'program_id' => null]);
    $existing->student->update(['cin' => '501012345679', 'last_name' => 'RAKOTO', 'first_names' => 'Test', 'birth_date' => '2004-10-12']);
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678'), pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test')], ImportKind::Pnes);
    expect($import->kind)->toBe(ImportKind::Pnes)->and($import->mapping)->toHaveKeys(['scholarship_code', 'uin', 'bank_account'])->and($import->valid_rows)->toBe(2);

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $created = Student::query()->where('scholarship_code', 'PN-001')->sole();
    $record = $created->studentRecords()->sole();
    expect($import->refresh()->matched_rows)->toBe(1)->and($import->created_rows)->toBe(1)
        ->and($record->source)->toBe(SituationSource::Pnes)->and($record->verification)->toBe(SituationVerification::Verified)->and($record->is_scholarship_holder)->toBeTrue()
        ->and($created->uin)->toBe('UINPN001')->and($created->bank_account)->toBe('00012345678901')
        ->and(DB::table('students')->where('id', $created->id)->value('bank_account'))->not->toBe('00012345678901');
    $existing->refresh();
    expect($existing->is_scholarship_holder)->toBeTrue()->and($existing->verification)->toBe(SituationVerification::Verified)->and($existing->matched_import_id)->toBe($import->id)
        ->and($existing->student->refresh()->scholarship_code)->toBe('PN-002');
});

it('creates at confirmation the establishments and doctoral levels a national file names, and recognises a known one by its name', function () {
    Establishment::factory()->create(['code' => 'EDSP', 'name' => 'Ecole de Droit et Science Politique (Antsohihy)']);
    $row = fn (string $code, string $cin, string $institution, string $institutionCode, string $level) => [$code, 'UIN'.str_replace('-', '', $code), '', 'NOM'.$code, 'Test', '12/10/2000', 'MERE', $cin, '', '', $institution, $institutionCode, 'Droit', 'Droit public', $level, 'N'];
    $import = importFile($this, $this->central, PNES_HEADERS, [
        $row('PN-1', '501012345671', 'EDSP Antsohihy', 'EDSPA', 'L2'),
        $row('PN-2', '501012345672', 'EDSP Mahajanga', 'EDSPM', 'D1'),
        $row('PN-3', '501012345673', 'EDSP Mahajanga', 'EDSPM', 'D2'),
    ], ImportKind::Pnes);
    expect($import->valid_rows)->toBe(3)->and($import->reference_changes['establishments'])->toBe(['EDSPM — EDSP Mahajanga'])
        ->and($import->reference_changes['levels'])->toBe(['D1', 'D2']);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $new = Establishment::query()->where('code', 'EDSPM')->sole();
    expect($import->refresh()->declared_rows)->toBe(3)->and($new->name)->toBe('EDSP Mahajanga')
        ->and(StudentRecord::query()->where('establishment_id', $new->id)->count())->toBe(2)
        ->and(StudentRecord::query()->whereHas('establishment', fn ($q) => $q->where('code', 'EDSP'))->count())->toBe(1)
        ->and(Level::query()->where('code', 'D2')->sole()->name)->toBe('Doctorat 2');
});

it('keeps a PNES scholarship holder whose contact is unusable or shared, and sets the contact aside', function () {
    $import = importFile($this, $this->central, PNES_HEADERS, [
        pnesRow('PN-001', 'RABE', '501012345678', '0341234567', 'rabe@example,test'),
        pnesRow('PN-002', 'RAKOTO', '501012345679', '0341234567', 'pas-un-email'),
        pnesRow('PN-003', 'RASOA', '501012345678'),
    ], ImportKind::Pnes);
    $rows = $import->rows()->orderBy('row_number')->get();
    expect($rows[0]->status->value)->toBe('valid')->and($rows[0]->normalized_data['email'])->toBe('rabe@example.test')
        ->and($rows[1]->status->value)->toBe('valid')->and($rows[1]->normalized_data['phone'])->toBeNull()->and($rows[1]->normalized_data['email'])->toBeNull()
        ->and($rows[1]->normalized_data['_dropped'])->toHaveCount(2)
        // A CIN shared by two scholarship holders stays a rejection.
        ->and($rows[2]->status->value)->toBe('rejected');
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.1.notices', fn ($notices) => collect($notices)->contains(fn ($text) => str_starts_with($text, 'Non repris — '))));
});

it('automatically verifies a central entry only when the establishment import is confirmed', function () {
    $data = ['last_name' => 'RABE', 'first_names' => 'Hery', 'sex' => 'M', 'birth_date' => '2003-04-05', 'mother_name' => 'MERE', 'nationality' => 'malagasy',
        'establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N'];
    $this->actingAs($this->central)->post('/students', $data)->assertSessionHasNoErrors()->assertRedirect();
    $record = StudentRecord::query()->sole();
    expect($record->source)->toBe(SituationSource::Central)->and($record->verification)->toBe(SituationVerification::Pending)
        ->and($record->is_scholarship_holder)->toBeFalse();
    // Not found in a list yet: nothing to validate.
    $this->post('/student-records/'.$record->uuid.'/verify')->assertSessionHasErrors();
    $this->actingAs($this->staff)->post('/student-records/'.$record->uuid.'/verify')->assertForbidden();

    $import = importFile($this, $this->staff, LIST_HEADERS, [['RABE', 'Hery', '05/04/2003', 'MERE', '', '', '', 'Faculté de Médecine', 'FACMED', 'Médecine', 'Médecine', 'L2', 'N']], ImportKind::Establishment, $this->facmed->id);
    expect($import->valid_rows)->toBe(1)
        ->and($record->refresh()->verification)->toBe(SituationVerification::Pending)
        ->and($record->status)->toBe(StudentRecordStatus::Pending);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.notices', fn ($notices) => collect($notices)->contains(fn ($text) => str_contains($text, 'après confirmation de l’import'))));
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(StudentRecord::query()->count())->toBe(1)->and($record->refresh()->verification)->toBe(SituationVerification::Verified)
        ->and($record->status)->toBe(StudentRecordStatus::Active)->and($record->source)->toBe(SituationSource::Central)
        ->and($record->is_scholarship_holder)->toBeFalse()
        ->and($record->matched_import_id)->toBe($import->id)->and($import->refresh()->matched_rows)->toBe(1);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.notices', fn ($notices) => collect($notices)->contains(fn ($text) => str_contains($text, 'lors de cet import')) && ! collect($notices)->contains(fn ($text) => str_contains($text, 'sera vérifiée'))));
    $log = Activity::query()->where('event', 'verified_by_import')->sole();
    expect($log->causer_id)->toBe($this->staff->id)->and($log->subject_id)->toBe($record->id)
        ->and($log->properties['import_id'])->toBe($import->id)->and($log->properties['changed_fields'])->toBe(['verification', 'status']);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(Activity::query()->where('event', 'verified_by_import')->count())->toBe(1);
    $this->actingAs($this->central)->get('/students?source=central&view=list')->assertInertia(fn (Assert $page) => $page
        ->where('records.total', 1)->where('records.data.0.status', 'active')->where('records.data.0.verification', 'verified')->where('stats.pending', 0)->where('stats.active', 1));
});

it('validates matched situations in bulk and skips the others', function () {
    $matched = StudentRecord::factory()->count(2)->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'source' => 'central', 'verification' => 'matched']);
    $pending = StudentRecord::factory()->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'source' => 'central', 'verification' => 'pending']);
    $this->actingAs($this->central)->post('/students/bulk', ['action' => 'verify', 'records' => [...$matched->pluck('uuid'), $pending->uuid]])->assertRedirect();
    expect(StudentRecord::query()->where('verification', 'verified')->count())->toBe(2)->and($pending->refresh()->verification)->toBe(SituationVerification::Pending);
});

it('imports the selected new bachelors in first year and ignores those not admitted', function () {
    $import = importFile($this, $this->central, PREINSCRIPTION_HEADERS, [
        ['D-0001', 'RABE', 'Hery', 'M', '05/04/2007', 'MERE', '', '0347654321', 'h@example.test', 'Faculté de Médecine', 'FACMED', 'Médecine', 'Médecine', '', 'Admis'],
        ['D-0002', 'RAKOTO', 'Soa', 'F', '06/04/2007', 'MERE', '', '0347654322', 's@example.test', 'Faculté de Médecine', 'FACMED', 'Médecine', 'Médecine', '', 'Liste d’attente'],
    ], ImportKind::Preinscription);
    expect($import->valid_rows)->toBe(1)->and(collect($import->rows()->orderBy('row_number')->get()[1]->errors)->pluck('code')->all())->toContain('not_admitted');
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $record = StudentRecord::query()->sole();
    expect($record->source)->toBe(SituationSource::Preinscription)->and($record->verification)->toBe(SituationVerification::Verified)
        ->and($record->preinscription_reference)->toBe('D-0001')->and($record->level->code)->toBe('L1')->and($record->repeat_code->value)->toBe('N');
});

it('shows UIN and bank account to the central office only, the scholarship code to everyone', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'is_scholarship_holder' => true, 'source' => 'pnes']);
    $record->student->update(['scholarship_code' => 'PN-777', 'uin' => 'UIN777', 'bank_account' => '00099988877766']);
    $this->actingAs($this->central)->get('/students/'.$record->student->uuid)->assertInertia(fn (Assert $page) => $page->where('student.scholarship_code', 'PN-777')->where('student.uin', 'UIN777')->where('student.bank_account', '00099988877766'));
    $this->actingAs($this->staff)->get('/students/'.$record->student->uuid)->assertInertia(fn (Assert $page) => $page->where('student.scholarship_code', 'PN-777')->missing('student.uin')->missing('student.bank_account'));
    // Searchable by UIN for the central office only.
    $this->get('/students?search=UIN777')->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
    $this->actingAs($this->central)->get('/students?search=UIN777')->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    $developer = User::factory()->create()->assignRole('developer');
    $this->actingAs($developer)->get('/students/'.$record->student->uuid)->assertInertia(fn (Assert $page) => $page->where('student.scholarship_code', 'PN-777')->missing('student.uin')->missing('student.bank_account'));
    // Never accepted from an establishment, even by a crafted request.
    $this->actingAs($this->staff);
    $this->put('/students/'.$record->student->uuid, ['uin' => 'UIN999', 'bank_account' => '1'])->assertSessionHasErrors(['uin', 'bank_account']);
    expect(json_encode($record->student->refresh()->toArray()))->not->toContain('UIN777')->not->toContain('00099988877766');
});

it('cleans the spreadsheet placeholders and the common email typos', function () {
    expect(StudentNormalization::value('email', 'Jean.Rakoto@Gmail,com'))->toBe('jean.rakoto@gmail.com')
        ->and(StudentNormalization::value('email', 'a@b;mg'))->toBe('a@b.mg')
        ->and(StudentNormalization::value('bank_account', 'N/A'))->toBeNull()
        ->and(StudentNormalization::value('uin', ' 12 34.56 '))->toBe('123456')
        ->and(StudentNormalization::value('last_name', 'X'))->toBe('X');
});

it('runs a large analysis and confirmation in the queue and follows its state', function () {
    config(['student_imports.queue_threshold' => 1]);
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678'), pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test')], ImportKind::Pnes);
    // The sync queue of the tests runs the job at once.
    expect($import->status->value)->toBe('ready')->and($import->valid_rows)->toBe(2);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($import->refresh()->status->value)->toBe('completed')->and(Student::query()->count())->toBe(2);
});

it('puts an interrupted queued analysis back to its previous step with a message', function () {
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678')], ImportKind::Pnes);
    $import->update(['status' => 'analyzing']);
    (new AnalyzeStudentImportJob($this->central, $import->refresh(), $import->mapping))->failed(new RuntimeException('timeout'));
    expect($import->refresh()->status->value)->toBe('ready')->and($import->failure_reason)->toContain('Relancez');
    app(AnalyzeStudentImport::class)->handle($this->central, $import, $import->mapping);
    expect($import->refresh()->failure_reason)->toBeNull();
});

it('shows the progress of a running analysis on the import page and clears it at the end', function () {
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678'), pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test')], ImportKind::Pnes);
    expect(ImportProgress::get($import))->toBeNull();
    $import->update(['status' => 'analyzing']);
    ImportProgress::put($import, 'rows', 1, 2);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('progress.phase', 'rows')->where('progress.percent', 50)->where('progress.idle', 0));
    $import->update(['status' => 'ready']);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('progress', null));
});

it('corrects a rejected row in the preview, keeps the uploaded file and analyses again', function () {
    $import = importFile($this, $this->central, PNES_HEADERS, [
        pnesRow('PN-001', 'RABE', '501012345678'),
        [...array_slice(pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test'), 0, 5), '31/02/2004', ...array_slice(pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test'), 6)],
    ], ImportKind::Pnes);
    $row = $import->rows()->orderBy('row_number')->get()[1];
    expect($import->valid_rows)->toBe(1)->and($row->status->value)->toBe('rejected');
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.1.values.birth_date', '2004-02-31')->where('rows.data.0.values', null));

    // Only mapped fields, only while the import is ready, never by an establishment.
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['unknown' => 'x']])->assertSessionHasErrors('values_keys.0');
    $this->actingAs($this->staff)->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['birth_date' => '28/02/2004']])->assertForbidden();

    $this->actingAs($this->central)->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['birth_date' => '28/02/2004']])->assertSessionHasNoErrors()->assertRedirect();
    $row->refresh();
    expect($import->refresh()->valid_rows)->toBe(2)->and($row->status->value)->toBe('valid')->and($row->corrections)->toBe(['birth_date' => '28/02/2004'])
        ->and($row->raw_data[5])->toBe('31/02/2004')
        ->and(Activity::query()->where('event', 'row_corrected')->sole()->properties->toArray())->toBe(['row_number' => 3, 'fields' => ['birth_date']]);

    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect(Student::query()->where('scholarship_code', 'PN-002')->sole()->birth_date->format('Y-m-d'))->toBe('2004-02-28');
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['birth_date' => '01/01/2004']])->assertSessionHasErrors('values');
});

it('ignores capitals and accents against the existing record and shows its value for a real difference', function () {
    $existing = StudentRecord::factory()->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id]);
    $existing->student->update(['cin' => '501012345678', 'last_name' => 'RABE', 'first_names' => 'Angèle Sealtiel', 'mother_name' => 'RASOA Volo', 'birth_date' => '2004-10-12']);
    $row = pnesRow('PN-001', 'RABE', '501012345678');
    $row[4] = 'angele  sealtiel';
    $row[6] = 'RASOA Vola';
    $import = importFile($this, $this->central, PNES_HEADERS, [$row], ImportKind::Pnes);
    $line = $import->rows()->sole();
    expect(collect($line->warnings)->pluck('field')->all())->toBe(['mother_name']);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.official', ['mother_name' => 'RASOA Volo']));
});

it('sends large analyses to the imports queue, apart from the OTP emails', function () {
    config(['student_imports.queue_threshold' => 1]);
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678'), pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test')], ImportKind::Pnes);
    Queue::fake();
    $this->post('/imports/'.$import->uuid.'/analyze', ['mapping' => $import->mapping])->assertSessionHasNoErrors();
    Queue::assertPushedOn('imports', AnalyzeStudentImportJob::class);
    expect($import->refresh()->status->value)->toBe('analyzing')->and(ImportProgress::get($import)['phase'])->toBe('waiting');
});

it('starts a worker from the import page when the queued analysis was taken by nobody', function () {
    config(['queue.auto_worker.enabled' => true]);
    Process::fake();
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678')], ImportKind::Pnes);
    $import->update(['status' => 'analyzing']);
    ImportProgress::put($import, 'waiting', 0, 1);
    $this->get('/imports/'.$import->uuid)->assertOk();
    Process::assertNothingRan();
    $this->travel(10)->seconds();
    $this->get('/imports/'.$import->uuid)->assertOk();
    Process::assertRanTimes(fn ($process) => str_contains($process->command, 'queue:work'), 1);
});

/** Existing student whose mother's name differs from the PNES file (« RASOA Volo » / « RASOA Vola »). */
function conflictingImport(TestCase $test, User $actor): array
{
    $existing = StudentRecord::factory()->create(['establishment_id' => $test->facmed->id, 'academic_year_id' => AcademicYear::factory()->create(['is_current' => false])->id, 'level_id' => $test->level->id]);
    $existing->student->update(['cin' => '501012345678', 'last_name' => 'RABE', 'first_names' => 'Test', 'mother_name' => 'RASOA Volo', 'birth_date' => '2004-10-12']);
    $row = pnesRow('PN-001', 'RABE', '501012345678');
    $row[6] = 'RASOA Vola';
    $import = importFile($test, $actor, PNES_HEADERS, [$row, pnesRow('PN-002', 'RAKOTO', '501012345679', '0341111111', 'y@example.test')], ImportKind::Pnes);

    return [$import, $existing->student, $import->rows()->orderBy('row_number')->first()];
}

it('imports alone, after confirmation, a row kept aside for a difference with the record', function () {
    [$import, $student, $row] = conflictingImport($this, $this->admin);
    expect($row->status->value)->toBe('warning');
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($import->refresh()->status->value)->toBe('completed')->and($import->declared_rows)->toBe(1)->and($import->skipped_rows)->toBe(1);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.official', ['mother_name' => 'RASOA Volo'])->where('rows.data.0.identity_editable', true)->where('rows.data.1.values', null));

    // « Garder la fiche » : the record value is taken, the row is imported alone.
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['mother_name' => 'RASOA Volo']])->assertSessionHasNoErrors();
    $import->refresh();
    expect($row->refresh()->resolution)->toBe('imported')->and($import->declared_rows)->toBe(2)->and($import->skipped_rows)->toBe(0)->and($import->warning_rows)->toBe(0)
        ->and($student->studentRecords()->where('academic_year_id', $this->year->id)->sole()->is_scholarship_holder)->toBeTrue()
        ->and($student->refresh()->mother_name)->toBe('RASOA Volo');
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => []])->assertSessionHasErrors('values');
});

it('updates the record with the file value when the agent chooses it and may correct the record', function () {
    [$import, $student, $row] = conflictingImport($this, $this->admin);
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['identity_updates' => ['mother_name']])->assertSessionHasNoErrors();
    expect($row->refresh()->status->value)->toBe('valid')->and($row->identity_updates)->toBe(['mother_name']);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.notices', fn ($notices) => collect($notices)->contains('La fiche sera mise à jour avec le fichier : Nom et prénoms de la mère.')));
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($student->refresh()->mother_name)->toBe('RASOA Vola')->and($import->refresh()->updated_rows)->toBe(1)
        ->and(Activity::query()->where('event', 'row_corrected')->sole()->properties['identity_updates'])->toBe(['mother_name']);
});

it('never lets an actor who may not correct the record replace it from an import', function () {
    // The central office corrects identities through students.update: without it, the import may not either.
    Role::findByName('central_scholarship_office')->revokePermissionTo('students.update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->central = $this->central->fresh();
    [$import, $student, $row] = conflictingImport($this, $this->central);
    $this->get('/imports/'.$import->uuid)->assertInertia(fn (Assert $page) => $page->where('rows.data.0.identity_editable', false));
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['identity_updates' => ['mother_name']])->assertSessionHasNoErrors();
    expect($row->refresh()->status->value)->toBe('warning')->and(collect($row->warnings)->pluck('code')->all())->toBe(['identity_update_forbidden']);
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['identity_updates' => ['address']])->assertSessionHasErrors('identity_updates.0');
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($student->refresh()->mother_name)->toBe('RASOA Volo');
});

it('imports alone a row rejected for a CIN shared in the file once its CIN is corrected', function () {
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678'), pnesRow('PN-002', 'RAKOTO', '501012345678', '0341111111', 'y@example.test')], ImportKind::Pnes);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    $row = $import->rows()->orderBy('row_number')->get()[1];
    expect($row->status->value)->toBe('rejected')->and($row->resolution)->toBeNull();
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => []])->assertSessionHasNoErrors();
    // Still the same CIN: it now names the imported student, whose record differs (name, codes): kept aside.
    expect($row->refresh()->resolution)->toBe('skipped')->and(collect($row->warnings)->where('code', 'official_conflict')->pluck('field')->all())->toContain('last_name');
    $this->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => ['cin' => '501012345679']])->assertSessionHasNoErrors();
    expect($row->refresh()->resolution)->toBe('imported')->and(Student::query()->where('cin', '501012345679')->sole()->scholarship_code)->toBe('PN-002')
        ->and($import->refresh()->rejected_rows)->toBe(0)->and($import->declared_rows)->toBe(2);
    $this->actingAs($this->staff)->patch('/imports/'.$import->uuid.'/rows/'.$row->id, ['values' => []])->assertForbidden();
});

it('shows where the students come from in the cards and the list, and filters both by origin', function () {
    foreach (['establishment' => 2, 'pnes' => 3, 'preinscription' => 1] as $source => $count) {
        StudentRecord::factory()->count($count)->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'source' => $source, 'is_scholarship_holder' => $source === 'pnes']);
    }
    StudentRecord::factory()->create(['establishment_id' => $this->other->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'source' => 'pnes', 'is_scholarship_holder' => true]);

    $this->actingAs($this->central)->get('/students')->assertInertia(fn (Assert $page) => $page->where('step', 'establishments')
        ->where('origins', [['value' => 'establishment', 'label' => 'Liste de l’établissement', 'count' => 2], ['value' => 'pnes', 'label' => 'Base PNES (boursiers)', 'count' => 4], ['value' => 'central', 'label' => 'Saisie de la Scolarité', 'count' => 0], ['value' => 'preinscription', 'label' => 'Préinscription (nouveau bachelier)', 'count' => 1]])
        ->where('cards.0.code', 'FACMED')->where('cards.0.active', 6)->where('cards.0.sources', ['establishment' => 2, 'pnes' => 3, 'central' => 0, 'preinscription' => 1]));

    // The origin narrows the cards, stays in the card steps, and the list.
    $this->get('/students?source=pnes')->assertInertia(fn (Assert $page) => $page->where('step', 'establishments')->where('filters.source', 'pnes')
        ->where('cards.0.active', 3)->where('cards.1.active', 1)->where('stats.total', 4)->where('trail.0.params.source', 'pnes')
        ->where('origins.1.count', 4)->where('origins.0.count', 2));
    $this->get('/students?view=list&source=preinscription')->assertInertia(fn (Assert $page) => $page->where('step', 'list')->has('records.data', 1)->where('records.data.0.source', 'preinscription'));
    $this->get('/students?source=unknown')->assertSessionHasErrors('source');
});

it('keeps a central entry « En attente » (not active) until it is validated, and never activates it by hand', function () {
    $data = ['last_name' => 'RABE', 'first_names' => 'Hery', 'sex' => 'M', 'birth_date' => '2003-04-05', 'mother_name' => 'MERE', 'nationality' => 'malagasy',
        'establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N', 'status' => 'active', 'scholarship_requested_at' => now()->toDateString()];
    $this->year->update(['name' => '2026-2027']);
    $this->actingAs($this->central)->post('/students', $data)->assertSessionHasNoErrors();
    $record = StudentRecord::query()->sole();
    expect($record->status)->toBe(StudentRecordStatus::Pending)
        ->and($record->scholarship_request_reference)->toBe('FACMED-2026-00001');
    $this->get('/students?view=list')->assertInertia(fn (Assert $page) => $page->where('stats.pending', 1)->where('stats.active', 0)->where('stats.inactive', 0)->where('records.data.0.status', 'pending'));
    $this->get('/students')->assertInertia(fn (Assert $page) => $page->where('cards.0.pending', 1)->where('cards.0.active', 0));

    $this->actingAs($this->admin)->patch('/student-records/'.$record->uuid.'/status', ['status' => 'active'])->assertSessionHasErrors('status');
    $this->post('/students/bulk', ['action' => 'activate', 'records' => [$record->uuid]]);
    expect($record->refresh()->status)->toBe(StudentRecordStatus::Pending);

    $record->update(['verification' => 'matched']);
    $this->actingAs($this->central)->post('/student-records/'.$record->uuid.'/verify')->assertSessionHasNoErrors();
    expect($record->refresh()->status)->toBe(StudentRecordStatus::Active)->and($record->verification)->toBe(SituationVerification::Verified);
});

it('activates a central entry the PNES base confirms', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->facmed->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'source' => 'central', 'verification' => 'pending', 'status' => 'pending']);
    $record->student->update(['cin' => '501012345678', 'last_name' => 'RABE', 'first_names' => 'Test', 'birth_date' => '2004-10-12', 'mother_name' => 'MERE Exemple']);
    $import = importFile($this, $this->central, PNES_HEADERS, [pnesRow('PN-001', 'RABE', '501012345678')], ImportKind::Pnes);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($record->refresh()->status)->toBe(StudentRecordStatus::Active)->and($record->is_scholarship_holder)->toBeTrue();
});

/** @return array{StudentRecord, StudentImport} */
function centralEntryWithList(TestCase $test, string $status = 'pending'): array
{
    $record = StudentRecord::factory()->create([
        'establishment_id' => $test->facmed->id, 'academic_year_id' => $test->year->id, 'level_id' => $test->level->id,
        'source' => 'central', 'status' => $status, 'verification' => 'pending',
    ]);
    $record->student->update(['last_name' => 'RABE', 'first_names' => 'Hery', 'birth_date' => '2003-04-05', 'mother_name' => 'MERE']);
    $import = importFile($test, $test->staff, LIST_HEADERS,
        [['RABE', 'Hery', '05/04/2003', 'MERE', '', '', '', 'Faculté de Médecine', 'FACMED', '', '', 'L2', 'N']], ImportKind::Establishment, $test->facmed->id);

    return [$record, $import];
}

it('verifies a match without reactivating an administratively inactive situation', function () {
    [$record, $import] = centralEntryWithList($this, 'inactive');
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($record->refresh()->verification)->toBe(SituationVerification::Verified)
        ->and($record->status)->toBe(StudentRecordStatus::Inactive);
});

it('rolls back automatic verification and row resolution if its audit fails', function () {
    [$record, $import] = centralEntryWithList($this);
    Activity::creating(function (Activity $activity) {
        if ($activity->event === 'verified_by_import') {
            throw new RuntimeException('Audit unavailable');
        }
    });
    try {
        $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasErrors('import');
    } finally {
        Activity::flushEventListeners();
    }
    expect($record->refresh()->verification)->toBe(SituationVerification::Pending)
        ->and($record->status)->toBe(StudentRecordStatus::Pending)
        ->and($import->refresh()->status->value)->toBe('failed')
        ->and($import->rows()->sole()->resolution)->toBeNull()
        ->and(Activity::query()->where('event', 'verified_by_import')->count())->toBe(0);
});

it('does not validate a scholarship application when its situation is verified by import', function () {
    [$record, $import] = centralEntryWithList($this);
    $application = app(ReceiveScholarshipApplication::class)->handle($this->central, $record->student_id, null, null);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($record->refresh()->status)->toBe(StudentRecordStatus::Active)
        ->and($record->verification)->toBe(SituationVerification::Verified)
        ->and($application->refresh()->status->value)->toBe('pending')
        ->and($application->decided_at)->toBeNull();
});

it('does not verify an inactive identity whose matching import line cannot be confirmed', function () {
    [$record, $import] = centralEntryWithList($this);
    // A change after preview is checked again under the import locks.
    $record->student->update(['is_active' => false]);
    $this->post('/imports/'.$import->uuid.'/commit')->assertSessionHasNoErrors();
    expect($record->refresh()->verification)->toBe(SituationVerification::Pending)
        ->and($record->status)->toBe(StudentRecordStatus::Pending)
        ->and($import->rows()->sole()->status->value)->toBe('warning')
        ->and(Activity::query()->where('event', 'verified_by_import')->count())->toBe(0);
});
