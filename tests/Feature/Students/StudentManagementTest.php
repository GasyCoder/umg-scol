<?php

use App\Actions\Students\CreateDeclaredStudent;
use App\Enums\RepeatCode;
use App\Enums\StudentRecordStatus;
use App\Enums\StudentSex;
use App\Models\AcademicYear;
use App\Models\Activity;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentNormalization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->a = Establishment::factory()->create();
    $this->b = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create();
    $this->level = Level::factory()->create();
    $this->program = Program::factory()->create(['establishment_id' => $this->a->id]);
    $this->actor = User::factory()->create();
    $this->actor->forceFill(['establishment_id' => $this->a->id])->save();
    $this->actor->assignRole('establishment');
    $this->admin = User::factory()->create()->assignRole('superadmin');
    $this->central = User::factory()->create()->assignRole('central_scholarship_office');
    $this->data = ['last_name' => ' Nom officiel ', 'first_names' => ' Prénom ', 'sex' => 'F', 'birth_date' => '2001-02-03',
        'mother_name' => ' Mère  officielle ', 'cin' => ' 101 234 567 890 ', 'nationality' => 'malagasy', 'phone' => ' 034 12 345 67 ',
        'email' => ' TEST@EXAMPLE.TEST ', 'address' => ' Adresse privée ', 'bac_series' => 'd', 'bac_year' => 2019,
        'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'repeat_code' => 'N'];
});

it('creates and normalizes identity with a first annual declaration and server actor', function () {
    $this->actingAs($this->actor)->post('/students', [...$this->data, 'repeat_code' => ' n (Nouveau) ', 'declared_by' => $this->admin->id, 'declared_at' => '1990-01-01'])->assertSessionHasNoErrors()->assertRedirect();
    $student = Student::sole();
    $record = StudentRecord::sole();
    expect($student->last_name)->toBe('Nom officiel')->and($student->first_names)->toBe('Prénom')
        ->and($student->email)->toBe('test@example.test')->and($student->cin)->toBe('101234567890')
        ->and($student->phone)->toBe('0341234567')->and($student->sex)->toBe(StudentSex::Female)
        ->and($student->mother_name)->toBe('Mère officielle')->and($student->nationality)->toBe('Malagasy')
        ->and($student->bac_series)->toBe('D')->and($record->repeat_code)->toBe(RepeatCode::New)->and($record->establishment_id)->toBe($this->a->id)
        ->and($record->declared_by)->toBe($this->actor->id)->and($record->status)->toBe(StudentRecordStatus::Active)
        ->and($record->declared_at->isToday())->toBeTrue();
    expect($record->student->is($student))->toBeTrue()->and($record->academicYear->is($this->year))->toBeTrue()
        ->and($record->establishment->is($this->a))->toBeTrue()->and($record->level->is($this->level))->toBeTrue()
        ->and($record->program->is($this->program))->toBeTrue()->and($record->declaredBy->is($this->actor))->toBeTrue()
        ->and($student->studentRecords()->count())->toBe(1)->and($this->a->studentRecords()->count())->toBe(1);
});

it('normalizes without damaging official names and Malagasy telephone formats', function () {
    expect(StudentNormalization::value('last_name', '  d’Andria-Rakoto  '))->toBe('d’Andria-Rakoto')
        ->and(StudentNormalization::value('phone', ' +261 (34) 12-345-67 '))->toBe('0341234567')
        ->and(StudentNormalization::value('cin', ' 101 234.567 890 '))->toBe('101234567890')
        ->and(StudentNormalization::value('first_names', '   '))->toBeNull();
});

it('allows authorized identity edits and status changes without deleting history', function () {
    $this->actingAs($this->actor)->post('/students', $this->data)->assertRedirect();
    $student = Student::sole();
    $this->get('/students/'.$student->uuid.'/edit')->assertOk();
    $this->put('/students/'.$student->uuid, [...$this->data, 'last_name' => 'Nom corrigé', 'is_active' => false, 'establishment_id' => $this->b->id])->assertSessionHasNoErrors()->assertRedirect();
    expect($student->fresh()->last_name)->toBe('Nom corrigé')->and($student->fresh()->is_active)->toBeTrue()
        ->and(StudentRecord::sole()->establishment_id)->toBe($this->a->id);
    $this->patch('/students/'.$student->uuid.'/status', ['is_active' => false])->assertRedirect();
    expect($student->fresh()->is_active)->toBeFalse()->and(StudentRecord::count())->toBe(1);
    $this->patch('/students/'.$student->uuid.'/status', ['is_active' => true])->assertRedirect();
    expect($student->fresh()->is_active)->toBeTrue();
    $this->delete('/students/'.$student->uuid)->assertStatus(405);
});

it('rejects invalid identity input', function (string $field, mixed $value) {
    $this->actingAs($this->actor)->post('/students', [...$this->data, $field => $value])->assertSessionHasErrors($field);
    expect(Student::count())->toBe(0)->and(StudentRecord::count())->toBe(0);
})->with([['last_name', ' '], ['email', 'invalid'], ['birth_date', '2999-01-01'], ['phone', 'invalid-phone'], ['bac_year', 1800], ['last_name', str_repeat('a', 151)]]);

it('rejects obvious identifier duplicates without exposing an existing student', function (string $field) {
    $other = Student::factory()->create([$field => StudentNormalization::value($field, $this->data[$field])]);
    StudentRecord::factory()->create(['student_id' => $other->id, 'establishment_id' => $this->b->id]);
    $this->actingAs($this->actor)->post('/students', $this->data)->assertSessionHasErrors($field);
    expect(Student::count())->toBe(1);
})->with(['cin', 'phone', 'email']);

it('rejects exact identity and birthdate matches and reused contact details but not a shared non-standard CIN', function () {
    Student::factory()->create(['last_name' => 'Nom officiel', 'first_names' => 'Prénom', 'birth_date' => '2001-02-03']);
    $this->actingAs($this->actor)->post('/students', $this->data)->assertSessionHasErrors('last_name');
    $this->post('/students', [...$this->data, 'last_name' => 'Autre nom'])->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/students', [...$this->data, 'last_name' => 'Encore autre', 'cin' => null])->assertSessionHasErrors('phone');
    $this->post('/students', [...$this->data, 'last_name' => 'Encore autre', 'cin' => null, 'phone' => null])->assertSessionHasErrors('email');
    $this->post('/students', [...$this->data, 'last_name' => 'Encore autre', 'cin' => 'RECU 123', 'phone' => null, 'email' => null])->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/students', [...$this->data, 'last_name' => 'Dernier autre', 'cin' => 'RECU 123', 'phone' => null, 'email' => null])->assertSessionHasNoErrors()->assertRedirect();
    expect(Student::count())->toBe(4)->and(Student::where('cin', 'RECU123')->count())->toBe(2);
});

it('rejects foreign establishment and program payloads', function () {
    $foreign = Program::factory()->create(['establishment_id' => $this->b->id]);
    $this->actingAs($this->actor)->post('/students', [...$this->data, 'establishment_id' => $this->b->id])->assertForbidden();
    $this->post('/students', [...$this->data, 'program_id' => $foreign->id])->assertSessionHasErrors('program_id');
    expect(Student::count())->toBe(0);
});

it('rejects nonexistent annual references', function (string $field) {
    $this->actingAs($this->admin)->post('/students', [...$this->data, 'establishment_id' => $this->a->id, $field => 99999999])->assertSessionHasErrors($field);
    expect(Student::count())->toBe(0);
})->with(['academic_year_id', 'establishment_id', 'level_id', 'program_id']);

it('rejects inactive reference data', function () {
    $this->level->update(['is_active' => false]);
    $this->actingAs($this->actor)->post('/students', $this->data)->assertSessionHasErrors('level_id');
    expect(Student::count())->toBe(0)->and(Activity::where('log_name', 'students')->count())->toBe(0);
});

it('keeps creation atomic including its audit if annual persistence fails', function () {
    StudentRecord::creating(function (): never {
        throw new RuntimeException('Simulated save failure');
    });
    $count = Activity::count();
    expect(fn () => app(CreateDeclaredStudent::class)->handle($this->actor, [...$this->data, 'establishment_id' => $this->a->id]))->toThrow(RuntimeException::class);
    expect(Student::count())->toBe(0)->and(StudentRecord::count())->toBe(0)->and(Activity::count())->toBe($count);
});

it('creates later years without overwriting previous years or attribution', function () {
    $this->actingAs($this->actor)->post('/students', $this->data)->assertRedirect();
    $student = Student::sole();
    $old = StudentRecord::sole();
    $next = AcademicYear::factory()->create();
    $payload = ['academic_year_id' => $next->id, 'level_id' => $this->level->id, 'program_id' => null, 'repeat_code' => 'R'];
    $this->get('/students/'.$student->uuid.'/records/create')->assertOk();
    $this->post('/students/'.$student->uuid.'/records', $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($student->studentRecords()->count())->toBe(2)->and($old->fresh()->academic_year_id)->toBe($this->year->id);
    $this->post('/students/'.$student->uuid.'/records', $payload)->assertSessionHasErrors('academic_year_id');
    $new = StudentRecord::where('academic_year_id', $next->id)->sole();
    $this->put('/student-records/'.$new->uuid, [...$payload, 'declared_by' => $this->admin->id])->assertRedirect();
    expect($new->fresh()->declared_by)->toBe($this->actor->id);
    $this->patch('/student-records/'.$new->uuid.'/status', ['status' => 'inactive'])->assertRedirect();
    expect($new->fresh()->status)->toBe(StudentRecordStatus::Inactive);
    $this->patch('/student-records/'.$new->uuid.'/status', ['status' => 'active'])->assertRedirect();
    expect($new->fresh()->status)->toBe(StudentRecordStatus::Active);
});

it('does not silently replace an annual establishment or year', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id]);
    $next = AcademicYear::factory()->create();
    $this->actingAs($this->admin)->put('/student-records/'.$record->uuid, ['establishment_id' => $this->b->id, 'academic_year_id' => $next->id, 'level_id' => $this->level->id, 'repeat_code' => 'N'])->assertSessionHasErrors('academic_year_id');
    expect($record->fresh()->establishment_id)->toBe($this->a->id);
});

it('guards annual database uniqueness and preserves inactive transfer history', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id]);
    expect(fn () => StudentRecord::factory()->create(['student_id' => $record->student_id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id]))->toThrow(QueryException::class);
    $record->update(['status' => 'inactive']);
    $transfer = StudentRecord::factory()->create(['student_id' => $record->student_id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id]);
    expect($record->fresh()->status)->toBe(StudentRecordStatus::Inactive)->and($transfer->status)->toBe(StudentRecordStatus::Active);
    expect(fn () => StudentRecord::factory()->create(['student_id' => $record->student_id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->a->id, 'status' => 'inactive']))->toThrow(QueryException::class);
});

it('enforces program establishment and all foreign keys in the database', function () {
    $foreign = Program::factory()->create(['establishment_id' => $this->b->id]);
    expect(fn () => StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'program_id' => $foreign->id]))->toThrow(QueryException::class);
    expect(fn () => StudentRecord::factory()->create(['student_id' => 9999999]))->toThrow(QueryException::class);
    $record = StudentRecord::factory()->create();
    expect(fn () => DB::table('students')->where('id', $record->student_id)->delete())->toThrow(QueryException::class);
});

it('strictly isolates student lists detail and foreign record urls', function () {
    $own = StudentRecord::factory()->create(['establishment_id' => $this->a->id]);
    $foreign = StudentRecord::factory()->create(['establishment_id' => $this->b->id]);
    $this->actingAs($this->actor)->get('/students?view=list')->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.student_id', $own->student_id));
    $this->get('/students/'.$own->student->uuid)->assertOk();
    foreach (['/students/'.$foreign->student->uuid, '/students/'.$foreign->student->uuid.'/edit', '/student-records/'.$foreign->uuid.'/edit', '/students/'.$foreign->student->uuid.'/records/create'] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->put('/students/'.$foreign->student->uuid, ['last_name' => 'Hack'])->assertForbidden();
    $this->patch('/students/'.$foreign->student->uuid.'/status', ['is_active' => false])->assertForbidden();
    $this->patch('/student-records/'.$foreign->uuid.'/status', ['status' => 'inactive'])->assertForbidden();
    $this->put('/student-records/'.$foreign->uuid, [])->assertForbidden();
    $this->get('/students?establishment_id='.$this->b->id)->assertForbidden();
});

it('does not reveal other annual records on a shared student detail', function () {
    $own = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'status' => 'inactive']);
    StudentRecord::factory()->create(['student_id' => $own->student_id, 'establishment_id' => $this->b->id]);
    $this->actingAs($this->actor)->get('/students/'.$own->student->uuid)->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('records.0.establishment_id', $this->a->id)->where('canUpdate', false)->where('canDeactivate', false));
    $this->put('/students/'.$own->student->uuid, ['last_name' => 'Changed'])->assertForbidden();
});

it('keeps central office read only while allowing global filters and detail', function () {
    $a = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id]);
    StudentRecord::factory()->create(['establishment_id' => $this->b->id]);
    $this->actingAs($this->central)->get('/students?view=list')->assertInertia(fn (Assert $page) => $page->has('records.data', 2)->where('canCreate', false));
    $this->get('/students/'.$a->student->uuid)->assertInertia(fn (Assert $page) => $page->where('canUpdate', false)->where('canCreateRecord', false));
    $query = http_build_query(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'program_id' => $this->program->id, 'status' => 'active']);
    $this->get('/students?'.$query)->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    $this->post('/students', $this->data)->assertForbidden();
    $this->put('/students/'.$a->student->uuid, ['last_name' => 'Hack'])->assertForbidden();
    $this->patch('/student-records/'.$a->uuid.'/status', ['status' => 'inactive'])->assertForbidden();
});

it('allows privileged global creation and retains developer audit identity', function (string $role) {
    $actor = User::factory()->create()->assignRole($role);
    $this->actingAs($actor)->post('/students', [...$this->data, 'establishment_id' => $this->b->id, 'program_id' => null])->assertSessionHasNoErrors()->assertRedirect();
    expect(StudentRecord::sole()->establishment_id)->toBe($this->b->id)->and(Activity::where('log_name', 'students')->sole()->causer_id)->toBe($actor->id);
})->with(['superadmin', 'developer']);

it('denies student accounts and accounts without permission including direct urls', function (string $role) {
    $user = User::factory()->create();
    if ($role !== 'none') {
        $user->assignRole($role);
    }
    $record = StudentRecord::factory()->create();
    $this->actingAs($user)->get('/students')->assertForbidden();
    $this->get('/students/'.$record->student->uuid)->assertForbidden();
    $this->get('/students/create')->assertForbidden();
    $this->post('/students', $this->data)->assertForbidden();
})->with(['student', 'none']);

it('audits changes without personal values in attributes or metadata', function () {
    $this->actingAs($this->actor)->post('/students', $this->data)->assertRedirect();
    $student = Student::sole();
    $record = StudentRecord::sole();
    $this->put('/students/'.$student->uuid, [...$this->data, 'last_name' => 'Correction sensible', 'email' => 'new-private@example.test'])->assertRedirect();
    $this->patch('/student-records/'.$record->uuid.'/status', ['status' => 'inactive'])->assertRedirect();
    $this->patch('/students/'.$student->uuid.'/status', ['is_active' => false])->assertRedirect();
    $logs = Activity::whereIn('log_name', ['students', 'student_records'])->get();
    expect($logs->count())->toBe(5);
    foreach ($logs as $log) {
        expect($log->causer_id)->toBe($this->actor->id);
        foreach (['Correction sensible', 'Nom officiel', 'Prénom', 'test@example.test', 'new-private@example.test', '101234567890', '0341234567', 'Adresse privée', '2001-02-03', 'Mère officielle'] as $sensitive) {
            expect($log->toJson())->not->toContain($sensitive);
        }
    }
    $modified = Activity::where('log_name', 'students')->where('event', 'updated')->first();
    expect($modified->properties->get('changed_fields'))->toContain('last_name', 'email');
});

it('searches all supported identity columns and hides sensitive list columns', function (string $field, string $term) {
    $student = Student::factory()->create([$field => $term]);
    StudentRecord::factory()->create(['student_id' => $student->id, 'establishment_id' => $this->a->id]);
    $this->actingAs($this->actor)->get('/students?'.http_build_query(['search' => $term]))->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->missing('records.data.0.student.cin')->missing('records.data.0.student.email')->missing('records.data.0.student.phone')->missing('records.data.0.student.mother_name')->missing('records.data.0.student.address'));
})->with([['last_name', 'Lookupnom'], ['first_names', 'Lookupprénom'], ['cin', '876543210123'], ['cin', 'RECU-LOOKUP'], ['phone', '0349999999'], ['email', 'lookup@example.test']]);

it('paginates on the server and bounds relation queries independently of row count', function () {
    StudentRecord::factory()->count(25)->create(['establishment_id' => $this->a->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($this->central)->get('/students?view=list')->assertInertia(fn (Assert $page) => $page->has('records.data', 20)->where('records.total', 25));
    $related = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "students"'));
    expect($related->count())->toBeLessThanOrEqual(2);
    DB::disableQueryLog();
});

it('rejects reactivation when another active declaration exists', function () {
    $old = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id, 'status' => 'inactive']);
    StudentRecord::factory()->create(['student_id' => $old->student_id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->b->id]);
    $this->actingAs($this->actor)->patch('/student-records/'.$old->uuid.'/status', ['status' => 'active'])->assertSessionHasErrors('academic_year_id');
    expect($old->fresh()->status)->toBe(StudentRecordStatus::Inactive);
});

it('protects historical program and declaration actor references in existing forms', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'program_id' => $this->program->id, 'declared_by' => $this->admin->id]);
    $this->actingAs($this->admin)->put('/references/programs/'.$this->program->uuid, ['establishment_id' => $this->b->id, 'name' => $this->program->name, 'is_active' => true])->assertSessionHasErrors('establishment_id');
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    expect($this->admin->fresh())->not->toBeNull()->and($record->fresh())->not->toBeNull();
});

it('rejects student-record alteration to a foreign program or establishment', function () {
    $own = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id]);
    $foreign = Program::factory()->create(['establishment_id' => $this->b->id]);
    $data = ['establishment_id' => $this->a->id, 'academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N'];
    $this->actingAs($this->actor)->put('/student-records/'.$own->uuid, [...$data, 'program_id' => $foreign->id])->assertSessionHasErrors('program_id');
    $this->put('/student-records/'.$own->uuid, [...$data, 'establishment_id' => $this->b->id])->assertForbidden();
});

it('does not create a declaration for inactive identities', function () {
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id]);
    $record->student->update(['is_active' => false]);
    $this->actingAs($this->actor)->post('/students/'.$record->student->uuid.'/records', ['academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N'])->assertSessionHasErrors('status');
});

it('escapes search wildcards consistently across database engines', function () {
    $student = Student::factory()->create(['cin' => 'SPEC%001']);
    StudentRecord::factory()->create(['student_id' => $student->id, 'establishment_id' => $this->a->id]);
    StudentRecord::factory()->create(['establishment_id' => $this->a->id]);
    $this->actingAs($this->actor)->get('/students?'.http_build_query(['search' => 'SPEC%']))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
});

it('rejects malformed manual establishment and status payloads', function () {
    $this->actingAs($this->actor)->post('/students', [...$this->data, 'establishment_id' => [$this->a->id]])->assertForbidden();
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id]);
    $this->patch('/student-records/'.$record->uuid.'/status', ['status' => 'registered'])->assertSessionHasErrors('status');
    expect($record->fresh()->status)->toBe(StudentRecordStatus::Active);
});

// Public uuid in URLs
it('identifies students by an uuid in every URL and never by the sequential id', function () {
    $this->actingAs($this->actor)->post('/students', $this->data)->assertSessionHasNoErrors();
    $student = Student::query()->latest('id')->first();
    expect($student->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and(route('students.show', $student))->toEndWith('/students/'.$student->uuid);
    $this->get('/students/'.$student->uuid)->assertOk()->assertInertia(fn (Assert $page) => $page->where('student.uuid', $student->uuid)->where('portalLinked', false));
    $this->get('/students/'.$student->id)->assertNotFound();
    $this->get('/students/not-a-uuid')->assertNotFound();
    $this->patch('/students/'.$student->id.'/status', ['is_active' => false])->assertNotFound();
    expect($student->fresh()->is_active)->toBeTrue();
    $record = $student->studentRecords()->first();
    $this->patch('/student-records/'.$record->uuid.'/status', ['status' => 'inactive'])->assertRedirect('/students/'.$student->uuid);
});
it('shows the mention of each declaration and whether a portal account is linked', function () {
    $mention = Mention::factory()->create(['establishment_id' => $this->a->id, 'name' => 'Sciences de la vie']);
    $this->program->update(['mention_id' => $mention->id]);
    $record = StudentRecord::factory()->create(['establishment_id' => $this->a->id, 'program_id' => $this->program->id]);
    User::factory()->create()->assignRole('student')->forceFill(['student_id' => $record->student_id])->save();
    $this->actingAs($this->actor)->get('/students/'.$record->student->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('records.0.program.mention.name', 'Sciences de la vie')->where('portalLinked', true)->missing('student.password'));
});
it('rejects implausible dates such as a year typed on one digit', function () {
    $this->actingAs($this->actor)->post('/students', [...$this->data, 'birth_date' => '0005-02-01', 'cin_issued_at' => '1899-12-31'])
        ->assertSessionHasErrors(['birth_date' => 'La date est antérieure à 1900 : vérifiez l’année saisie.', 'cin_issued_at']);
});
