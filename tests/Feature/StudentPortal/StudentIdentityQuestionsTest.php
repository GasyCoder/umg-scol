<?php

use App\Actions\StudentPortal\LinkStudent;
use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\StudentIdentityQuestions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
    $this->base = 'http://'.config('portals.bourse_domain').'/api/student';
    $this->student = Student::factory()->create(['email' => 'hery@example.test', 'birth_date' => '2003-05-06', 'cin' => null, 'phone' => '0331234567', 'mother_name' => null, 'bac_year' => 2021]);
    $this->user = User::factory()->create(['email' => $this->student->email])->assignRole('student');
});

function questionSession(TestCase $test): TestCase
{
    return $test->actingAs($test->user, 'student')->withSession(['student_portal' => true]);
}

/** Right answer for each issued question, from what the test knows of the record. */
function rightAnswers(array $questions, array $truth): array
{
    $answers = [];
    foreach ($questions as $question) {
        if ($question['kind'] === 'choice') {
            $option = collect($question['options'])->first(fn ($option) => in_array($option['label'], $truth, true));
            $answers[$question['id']] = $option['value'];
        } else {
            $answers[$question['id']] = str_contains($question['label'], '4 derniers') ? '4567' : '0331234567';
        }
    }

    return $answers;
}

it('asks a typed proof and a multiple-choice question without CIN, never sending official values', function () {
    questionSession($this);
    $response = $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->assertOk()->assertJsonCount(2, 'questions')
        ->assertJsonPath('questions.0.kind', 'text')->assertJsonPath('questions.1.kind', 'choice')->assertJsonPath('questions.1.label', 'En quelle année avez-vous eu le BAC ?')
        ->assertJsonCount(5, 'questions.1.options')->assertJsonPath('remaining', 5);
    $years = array_column($response->json('questions.1.options'), 'label');
    expect($years)->toContain('2021')->and((int) max($years))->toBeLessThanOrEqual(now()->year)
        ->and($response->getContent())->not->toContain('0331234567', '2003-05-06');
    $this->postJson($this->base.'/link', ['answers' => rightAnswers($response->json('questions'), ['2021']), 'birth_date' => '2003-05-06'])->assertOk()->assertJsonPath('linked', true);
});

it('keeps the same draw and options across reloads and logins until a success', function () {
    questionSession($this);
    $first = $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->json('questions');
    $this->postJson($this->base.'/link', ['answers' => ['q1' => '0000', 'q2' => '0'], 'birth_date' => '2003-05-06'])->assertJsonPath('linked', false);
    $this->travel(2)->minutes();
    expect($this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->json('questions'))->toBe($first);
    $this->postJson($this->base.'/link', ['answers' => rightAnswers($first, ['2021']), 'birth_date' => '2003-05-06'])->assertJsonPath('linked', true);
    // Drawn again after the success: the cached draw is gone.
    expect(StudentIdentityQuestions::pending($this->user, $this->student))->toBeFalse();
});

it('locks the verification after five refusals, whatever the answers given next', function () {
    $questions = StudentIdentityQuestions::issue($this->user, $this->student);
    for ($i = 0; $i < StudentIdentityQuestions::MAX_FAILURES; $i++) {
        expect(app(LinkStudent::class)->handle($this->user, ['q1' => '9999', 'q2' => '0'], '2003-05-06'))->toBeFalse();
    }
    expect(app(LinkStudent::class)->handle($this->user, rightAnswers($questions, ['2021']), '2003-05-06'))->toBeFalse()
        ->and($this->user->fresh()->student_id)->toBeNull()
        ->and(Activity::query()->where('event', 'identity_check_locked')->count())->toBe(1);
    questionSession($this);
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    $this->travel(13)->hours();
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->assertOk()->assertJsonPath('remaining', 5);
});

it('asks three multiple-choice questions built from reference lists when no proof is recorded', function () {
    $this->student->update(['phone' => null, 'bac_series' => 'D']);
    $year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_current' => true]);
    $mine = Establishment::factory()->create(['name' => 'Faculté des Sciences']);
    Establishment::factory()->count(5)->create();
    $level = Level::factory()->create(['name' => 'Licence 2']);
    StudentRecord::factory()->create(['student_id' => $this->student->id, 'academic_year_id' => $year->id, 'establishment_id' => $mine->id, 'level_id' => $level->id]);
    questionSession($this);
    $questions = $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->assertOk()->assertJsonCount(3, 'questions')->json('questions');
    expect(array_column($questions, 'kind'))->toBe(['choice', 'choice', 'choice'])
        ->and(collect($questions)->pluck('options')->flatten(1)->pluck('label')->filter(fn ($label) => in_array($label, ['2021', 'D', 'Faculté des Sciences'], true))->count())->toBe(3);
    $this->postJson($this->base.'/link', ['answers' => rightAnswers($questions, ['2021', 'D', 'Faculté des Sciences']), 'birth_date' => '2003-05-06'])->assertOk()->assertJsonPath('linked', true);
});

it('asks nothing when the record holds too little to verify', function () {
    $this->student->update(['phone' => null, 'bac_year' => null]);
    questionSession($this);
    $this->postJson($this->base.'/link/birth-date', ['birth_date' => '2003-05-06'])->assertOk()->assertJsonCount(0, 'questions');
    $this->postJson($this->base.'/link', ['answers' => ['q1' => 'x'], 'birth_date' => '2003-05-06'])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
});
