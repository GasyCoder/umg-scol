<?php

use App\Actions\Students\SaveStudentIdentity;
use App\Enums\RepeatCode;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\ScholarshipEligibility;
use App\Support\StudentNormalization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->establishment = Establishment::factory()->create();
    $this->year = AcademicYear::factory()->create(['scholarship_max_bac_year' => 2025]);
    $this->level = Level::factory()->create();
    $this->staff = User::factory()->create()->assignRole('establishment');
    $this->staff->forceFill(['establishment_id' => $this->establishment->id])->save();
});

/** Unsaved models: the rule reads attributes and the loaded academic year only. */
function eligibility(array $student = [], array $record = [], ?int $limit = 2025): array
{
    $year = new AcademicYear(['name' => '2025-2026', 'scholarship_max_bac_year' => $limit]);
    $declaration = new StudentRecord(['repeat_code' => 'N', 'scholarship_request_reference' => 'REF-1', ...$record]);
    $declaration->setRelation('academicYear', $year);

    return ScholarshipEligibility::evaluate(new Student(['nationality' => 'Malagasy', 'bac_year' => 2025, ...$student]), $declaration);
}

// Scholarship eligibility (indicative rule of the establishment file)
it('evaluates the scholarship rule of the establishment file', function (array $student, array $record, ?int $limit, string $status, string $rate, ?string $reason) {
    $result = eligibility($student, $record, $limit);
    expect($result['status'])->toBe($status)->and($result['rate'])->toBe($rate);
    if ($reason === null) {
        expect($result['reasons'])->toBe([]);
    } else {
        expect(implode(' ', $result['reasons']))->toContain($reason);
    }
})->with([
    'new student' => [[], ['repeat_code' => 'N'], 2025, ScholarshipEligibility::ELIGIBLE, '1', null],
    'repeating student' => [[], ['repeat_code' => 'R'], 2025, ScholarshipEligibility::ELIGIBLE, '1/4', null],
    'BAC older than the limit' => [['bac_year' => 2020], [], 2025, ScholarshipEligibility::ELIGIBLE, '1', null],
    'triple or more' => [[], ['repeat_code' => 'T+'], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Triplant ou plus'],
    'missing repeat code' => [[], ['repeat_code' => null], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Code de redoublement non renseigné'],
    'not Malagasy' => [['nationality' => 'Comorien'], [], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Nationalité non malagasy'],
    'no nationality' => [['nationality' => null], [], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Nationalité'],
    'no request reference' => [[], ['scholarship_request_reference' => null], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Aucune référence de demande'],
    'BAC after the limit' => [['bac_year' => 2026], [], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'BAC obtenu après 2025'],
    'no BAC year' => [['bac_year' => null], [], 2025, ScholarshipEligibility::NOT_ELIGIBLE, '0', 'Année du BAC non renseignée'],
    'limit not set' => [[], [], null, ScholarshipEligibility::UNDETERMINED, '0', 'Année limite du BAC non définie'],
]);

it('keeps the failing reasons ahead of an undetermined limit', function () {
    $result = eligibility(['nationality' => 'Autres'], ['repeat_code' => 'T+'], null);
    expect($result['status'])->toBe(ScholarshipEligibility::NOT_ELIGIBLE)->and($result['reasons'])->toHaveCount(2);
});

it('shows the indicative eligibility per declaration and flags a non-standard CIN on the student page', function (string $cin, bool $standard) {
    $student = Student::factory()->create(['cin' => $cin, 'nationality' => 'Malagasy', 'bac_year' => 2024]);
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'level_id' => $this->level->id, 'repeat_code' => 'R', 'scholarship_request_reference' => 'REF-7']);
    $this->actingAs($this->staff)->get('/students/'.$student->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('student.cin_is_standard', $standard)
        ->where('records.0.repeat_code', 'R')->where('records.0.scholarship_request_reference', 'REF-7')
        ->where('records.0.eligibility.status', ScholarshipEligibility::ELIGIBLE)->where('records.0.eligibility.rate', '1/4'));
})->with([['401011012345', true], ['RECU-12', false], ['40101101234', false]]);

// Normalization of the establishment file values
it('normalizes phone numbers to the national form', function (string $input, string $expected) {
    expect(StudentNormalization::value('phone', $input))->toBe($expected);
})->with([
    'numeric cell without leading 0' => ['327407096', '0327407096'],
    'national form' => ['032 74 070 96', '0327407096'],
    'international prefix' => ['+261 32 74 070 96', '0327407096'],
    'international without plus' => ['261327407096', '0327407096'],
    'double zero prefix' => ['00261327407096', '0327407096'],
    'foreign number kept' => ['+33612345678', '+33612345678'],
    'short number kept' => ['12345', '12345'],
]);

it('normalizes sex, repeat code, nationality and BAC series to the template lists', function (string $field, string $input, ?string $expected) {
    expect(StudentNormalization::value($field, $input))->toBe($expected);
})->with([
    ['sex', 'M', 'M'], ['sex', ' f ', 'F'], ['sex', 'Masculin', 'M'], ['sex', 'Féminin', 'F'], ['sex', 'homme', 'M'], ['sex', 'Fille', 'F'], ['sex', 'X', 'X'], ['sex', '  ', null],
    ['repeat_code', 'N (Nouveau)', 'N'], ['repeat_code', 'r (redoublant)', 'R'], ['repeat_code', 'T+ (Triplant ou plus)', 'T+'], ['repeat_code', ' t+ ', 'T+'], ['repeat_code', 'Z', 'Z'],
    ['nationality', 'malagasy', 'Malagasy'], ['nationality', 'europeen', 'Européen'], ['nationality', 'Martienne', 'Martienne'],
    ['bac_series', 'a2', 'A2'], ['bac_series', 'tech. industriel', 'Tech. Industriel'],
]);

it('flags only twelve-digit CIN numbers as standard', function () {
    expect(StudentNormalization::isStandardCin('401011012345'))->toBeTrue()
        ->and(StudentNormalization::isStandardCin('40101101234'))->toBeFalse()
        ->and(StudentNormalization::isStandardCin('RECU-401011012345'))->toBeFalse()
        ->and(StudentNormalization::isStandardCin(null))->toBeFalse()
        ->and(RepeatCode::parse('N (Nouveau)'))->toBe(RepeatCode::New)->and(RepeatCode::parse(12))->toBeNull();
});

// Uniqueness of the identity
it('rejects a phone or email already used by another student when saving an identity', function (string $field, string $value, string $message) {
    Student::factory()->create([$field => $value]);
    $student = Student::factory()->create(['phone' => null]);
    $actor = User::factory()->create()->assignRole('superadmin');
    try {
        app(SaveStudentIdentity::class)->handle($actor, $student, [$field => $value]);
        $this->fail('The duplicate was accepted.');
    } catch (ValidationException $error) {
        expect($error->errors())->toBe([$field => [$message]]);
    }
    expect(Student::query()->where($field, StudentNormalization::value($field, $value))->count())->toBe(1);
    // The same value stays allowed on its own identity.
    $owner = Student::query()->where($field, StudentNormalization::value($field, $value))->sole();
    expect(app(SaveStudentIdentity::class)->handle($actor, $owner, [$field => $value, 'last_name' => 'Renommé'])->last_name)->toBe('Renommé');
})->with([
    ['phone', '0341234567', 'Ce numéro de téléphone est déjà utilisé par un autre étudiant.'],
    ['email', 'shared@example.test', 'Cette adresse mail est déjà utilisée par un autre étudiant.'],
]);

it('accepts a non-standard CIN shared by two identities but not a shared standard CIN', function () {
    $actor = User::factory()->create()->assignRole('superadmin');
    Student::factory()->create(['cin' => 'RECU-1']);
    Student::factory()->create(['cin' => '401011012345']);
    $student = Student::factory()->create();
    expect(app(SaveStudentIdentity::class)->handle($actor, $student, ['cin' => 'RECU-1'])->cin)->toBe('RECU-1');
    expect(fn () => app(SaveStudentIdentity::class)->handle($actor, $student, ['cin' => '4010 1101 2345']))->toThrow(ValidationException::class);
    expect($student->fresh()->cin)->toBe('RECU-1');
});

// Scholarship request reference of the annual declaration
it('rejects a scholarship request reference already given in the same establishment and year', function () {
    StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'scholarship_request_reference' => 'DB-2026-001']);
    $student = Student::factory()->create();
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => $this->establishment->id]);
    $payload = ['academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'N', 'scholarship_requested_at' => '2026-10-01', 'scholarship_request_reference' => ' DB-2026-001 '];
    $this->actingAs($this->staff)->post('/students/'.$student->uuid.'/records', $payload)->assertSessionHasErrors(['scholarship_request_reference' => 'Cette référence de demande de bourse est déjà attribuée dans l’établissement pour cette année.']);
    expect($student->studentRecords()->count())->toBe(1);
    $this->post('/students/'.$student->uuid.'/records', [...$payload, 'scholarship_request_reference' => 'DB-2026-002'])->assertSessionHasNoErrors()->assertRedirect();
    $record = $student->studentRecords()->where('academic_year_id', $this->year->id)->sole();
    expect($record->scholarship_request_reference)->toBe('DB-2026-002')->and($record->scholarship_requested_at->format('Y-m-d'))->toBe('2026-10-01')->and($record->repeat_code)->toBe(RepeatCode::New);
    // Editing the declaration keeps its own reference.
    $this->put('/student-records/'.$record->uuid, [...$payload, 'scholarship_request_reference' => 'DB-2026-002', 'repeat_code' => 'R'])->assertSessionHasNoErrors();
    expect($record->fresh()->repeat_code)->toBe(RepeatCode::Repeating);
});

it('allows the same request reference in another establishment or year and enforces it in SQL', function () {
    $existing = StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'establishment_id' => $this->establishment->id, 'scholarship_request_reference' => 'DB-1']);
    StudentRecord::factory()->create(['academic_year_id' => $this->year->id, 'scholarship_request_reference' => 'DB-1']);
    StudentRecord::factory()->create(['establishment_id' => $this->establishment->id, 'scholarship_request_reference' => 'DB-1']);
    expect(fn () => StudentRecord::factory()->create(['academic_year_id' => $existing->academic_year_id, 'establishment_id' => $existing->establishment_id, 'scholarship_request_reference' => 'DB-1']))->toThrow(QueryException::class);
});

it('requires the repeat code and a reference when a deposit date is given', function () {
    $student = Student::factory()->create();
    StudentRecord::factory()->create(['student_id' => $student->id, 'academic_year_id' => AcademicYear::factory()->create()->id, 'establishment_id' => $this->establishment->id]);
    $this->actingAs($this->staff)->post('/students/'.$student->uuid.'/records', ['academic_year_id' => $this->year->id, 'level_id' => $this->level->id, 'repeat_code' => 'Q', 'scholarship_requested_at' => '2026-10-01'])
        ->assertSessionHasErrors(['repeat_code', 'scholarship_request_reference']);
    expect($student->studentRecords()->count())->toBe(1);
});
