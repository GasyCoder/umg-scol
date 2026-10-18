<?php

namespace App\Support;

use App\Models\Establishment;
use App\Models\Level;
use App\Models\Mention;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Identity questions drawn for each student from their own official record (ADR-087).
 * CIN when recorded; otherwise one typed proof and one multiple-choice question picked at random
 * (three choice questions when no proof is recorded). Wrong choices come from the reference lists, never
 * from another student. The draw, option order included, is kept on the server until success or expiry:
 * reloading never offers an easier question, nor successive option lists to compare.
 */
final class StudentIdentityQuestions
{
    public const MAX_FAILURES = 5;

    private const TTL_HOURS = 12;

    private const CHOICES = 5;

    private const PROOFS = ['phone', 'mother_name', 'scholarship_code', 'preinscription_reference'];

    /**
     * Questions for the client, without any official value. Empty when the record holds too little to ask.
     *
     * @return list<array{id:string,kind:string,label:string,hint:string|null,input:string,max_length:int,options?:list<array{value:string,label:string}>}>
     */
    public static function issue(User $user, Student $student): array
    {
        $challenge = self::challenge($user);
        if ($challenge === null || $challenge['student_id'] !== $student->id || ! self::stillValid($student, $challenge['questions'])) {
            $challenge = [
                'student_id' => $student->id,
                'questions' => self::draw($student),
                'failures' => $challenge['failures'] ?? 0,
                'expires_at' => $challenge['expires_at'] ?? now()->addHours(self::TTL_HOURS)->getTimestamp(),
            ];
            self::store($user, $challenge);
        }

        return array_map(function (array $question, int $index): array {
            $public = ['id' => 'q'.($index + 1), 'kind' => isset($question['options']) ? 'choice' : 'text', 'label' => $question['label'], 'hint' => $question['hint'] ?? null, 'input' => $question['input'] ?? 'text', 'max_length' => $question['max_length'] ?? 255];
            foreach ($question['options'] ?? [] as $value => $label) {
                $public['options'][] = ['value' => (string) $value, 'label' => $label];
            }

            return $public;
        }, $challenge['questions'], array_keys($challenge['questions']));
    }

    public static function pending(User $user, Student $student): bool
    {
        $challenge = self::challenge($user);

        return $challenge !== null && $challenge['student_id'] === $student->id && $challenge['questions'] !== [];
    }

    public static function locked(User $user): bool
    {
        return (self::challenge($user)['failures'] ?? 0) >= self::MAX_FAILURES;
    }

    public static function remaining(User $user): int
    {
        return max(0, self::MAX_FAILURES - (self::challenge($user)['failures'] ?? 0));
    }

    /** @param array<string,mixed> $answers keyed q1, q2… as issued */
    public static function passes(User $user, Student $student, array $answers): bool
    {
        $challenge = self::challenge($user);
        if ($challenge === null || $challenge['student_id'] !== $student->id || $challenge['questions'] === [] || self::locked($user)) {
            return false;
        }
        $passed = true;
        $record = self::latestRecord($student);
        // Every answer is checked, so the response time does not tell which one was wrong.
        foreach ($challenge['questions'] as $index => $question) {
            $answer = $answers['q'.($index + 1)] ?? null;
            $passed = (is_string($answer) && self::matches($student, $record, $question, $answer)) && $passed;
        }

        return $passed;
    }

    /** Counts a failed final check; the draw stays the same and is locked after MAX_FAILURES. */
    public static function fail(User $user): void
    {
        $challenge = self::challenge($user);
        if ($challenge === null) {
            return;
        }
        $challenge['failures']++;
        self::store($user, $challenge);
        if ($challenge['failures'] === self::MAX_FAILURES) {
            StudentPortalAccess::audit($user, 'identity_check_locked', $challenge['student_id']);
        }
    }

    /** After a success: the next login draws new questions. */
    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    /** @return list<array<string,mixed>> */
    private static function draw(Student $student): array
    {
        if (filled($student->cin)) {
            return [self::proof('cin', $student)];
        }
        $proofs = array_values(array_filter(self::PROOFS, fn (string $type) => self::proofAvailable($student, $type)));
        $record = self::latestRecord($student);
        $choices = Arr::shuffle(array_values(array_filter(array_map(fn (string $type) => self::choice($student, $record, $type), ['bac_year', 'bac_series', 'establishment', 'level', 'mention']))));
        if ($proofs === []) {
            return count($choices) >= 3 ? array_values(array_slice($choices, 0, 3)) : [];
        }
        $proofs = Arr::shuffle($proofs);
        $questions = [self::proof($proofs[0], $student)];
        if ($choices !== []) {
            $questions[] = $choices[0];
        } elseif (isset($proofs[1])) {
            $questions[] = self::proof($proofs[1], $student);
        }

        return $questions;
    }

    /** @return array<string,mixed> */
    private static function proof(string $type, Student $student): array
    {
        // Two wordings for the phone, so that the same question is not always asked.
        if ($type === 'phone' && random_int(0, 1) === 1) {
            return ['type' => 'phone_end', 'label' => 'Les 4 derniers chiffres de votre téléphone', 'hint' => 'Le numéro déclaré à votre établissement.', 'input' => 'numeric', 'max_length' => 4];
        }

        return match ($type) {
            'cin' => ['type' => 'cin', 'label' => 'Numéro de votre CIN', 'hint' => 'Tel qu’il figure sur votre carte.', 'input' => $student->hasStandardCin() ? 'numeric' : 'text', 'max_length' => 20],
            'phone' => ['type' => 'phone', 'label' => 'Votre numéro de téléphone', 'hint' => 'Le numéro déclaré à votre établissement.', 'input' => 'tel', 'max_length' => 20],
            'mother_name' => ['type' => 'mother_name', 'label' => 'Nom et prénoms de votre mère', 'hint' => 'Tels que déclarés à votre établissement.'],
            'scholarship_code' => ['type' => 'scholarship_code', 'label' => 'Votre code bourse PNES', 'hint' => 'Il figure sur votre document PNES.'],
            'preinscription_reference' => ['type' => 'preinscription_reference', 'label' => 'Votre numéro de dossier de préinscription', 'hint' => 'Il figure sur votre résultat de préinscription.'],
            default => throw new InvalidArgumentException("Unknown identity proof {$type}."),
        };
    }

    private static function proofAvailable(Student $student, string $type): bool
    {
        return match ($type) {
            'cin' => filled($student->cin),
            'phone', 'phone_end' => filled($student->phone),
            'mother_name' => filled($student->mother_name),
            'scholarship_code' => filled($student->scholarship_code),
            'preinscription_reference' => $student->studentRecords()->whereNotNull('preinscription_reference')->where('preinscription_reference', '<>', '')->exists(),
            default => false,
        };
    }

    /** @return array<string,mixed>|null a question with the true value among reference-list decoys */
    private static function choice(Student $student, ?StudentRecord $record, string $type): ?array
    {
        $correct = self::correct($student, $type, $record);
        if ($correct === null) {
            return null;
        }
        $year = $record?->academicYear?->name;
        if ($type === 'bac_year') {
            // Five consecutive years, the true one at a random place, never in the future.
            $start = min((int) $correct - random_int(0, self::CHOICES - 1), (int) now()->year - self::CHOICES + 1);

            return ['type' => $type, 'label' => 'En quelle année avez-vous eu le BAC ?', 'options' => array_map('strval', range($start, $start + self::CHOICES - 1))];
        }
        // The student's own past values are never decoys: only one option can be theirs.
        [$label, $decoys] = match ($type) {
            'bac_series' => ['Quelle est la série de votre BAC ?', array_diff(StudentReferenceLists::BAC_SERIES, ['Autres'])],
            'establishment' => ["Quel est votre établissement en {$year} ?",
                Establishment::query()->where('is_active', true)->whereNotIn('id', $student->studentRecords()->select('establishment_id'))->pluck('name')->all()],
            'level' => ["Quel est votre niveau en {$year} ?",
                Level::query()->where('is_active', true)->whereNotIn('id', $student->studentRecords()->select('level_id'))->pluck('name')->all()],
            'mention' => ["Quelle est votre mention en {$year} ?",
                Mention::query()->where('is_active', true)->where('establishment_id', $record?->establishment_id)
                    ->whereNotIn('id', $student->studentRecords()->join('programs', 'programs.id', '=', 'student_records.program_id')->select('programs.mention_id'))->pluck('name')->all()],
            default => throw new InvalidArgumentException("Unknown identity choice {$type}."),
        };
        $decoys = array_values(array_unique(array_filter($decoys, fn ($name) => filled($name) && StudentImportColumns::fold((string) $name) !== StudentImportColumns::fold($correct))));
        if (count($decoys) < self::CHOICES - 1) {
            return null;
        }

        return ['type' => $type, 'label' => $label, 'options' => Arr::shuffle([$correct, ...Arr::random($decoys, self::CHOICES - 1)])];
    }

    private static function correct(Student $student, string $type, ?StudentRecord $record): ?string
    {
        $value = match ($type) {
            'bac_year' => $student->bac_year !== null && $student->bac_year >= 1950 && $student->bac_year <= now()->year ? (string) $student->bac_year : null,
            'bac_series' => in_array($student->bac_series, array_diff(StudentReferenceLists::BAC_SERIES, ['Autres']), true) ? $student->bac_series : null,
            'establishment' => $record?->establishment?->name,
            'level' => $record?->level?->name,
            'mention' => $record?->program?->mention?->name,
            default => null,
        };

        return filled($value) ? (string) $value : null;
    }

    private static function latestRecord(Student $student): ?StudentRecord
    {
        // Most recent academic year first; a year without start date comes last.
        return StudentRecord::query()->where('student_records.student_id', $student->id)
            ->join('academic_years', 'academic_years.id', '=', 'student_records.academic_year_id')
            ->orderByRaw('academic_years.starts_at IS NULL')->orderByDesc('academic_years.starts_at')->orderByDesc('academic_years.id')
            ->select('student_records.*')->with(['academicYear', 'establishment', 'level', 'program.mention'])->first();
    }

    /** @param array<string,mixed> $question */
    private static function matches(Student $student, ?StudentRecord $record, array $question, string $answer): bool
    {
        if (isset($question['options'])) {
            $chosen = ctype_digit($answer) ? ($question['options'][(int) $answer] ?? null) : null;
            $correct = self::correct($student, $question['type'], $record);

            return $chosen !== null && $correct !== null && hash_equals(StudentImportColumns::fold($correct), StudentImportColumns::fold($chosen));
        }
        $type = $question['type'];
        if (! self::proofAvailable($student, $type)) {
            return false;
        }
        if ($type === 'phone_end') {
            return preg_match('/^\d{4}$/', $answer) === 1 && hash_equals(substr((string) $student->phone, -4), $answer);
        }
        $value = StudentNormalization::value($type, $answer);
        if (blank($value)) {
            return false;
        }

        return match ($type) {
            'preinscription_reference' => $student->studentRecords()->where('preinscription_reference', $value)->exists(),
            'mother_name' => hash_equals(StudentImportColumns::fold((string) $student->mother_name), StudentImportColumns::fold((string) $value)),
            default => hash_equals((string) $student->getAttribute($type), (string) $value),
        };
    }

    /**
     * A staff correction can remove an asked value: the draw is then replaced (failures kept).
     *
     * @param  list<array<string,mixed>>  $questions
     */
    private static function stillValid(Student $student, array $questions): bool
    {
        $record = self::latestRecord($student);
        if (filled($student->cin) !== (($questions[0]['type'] ?? null) === 'cin')) {
            return false;
        }
        foreach ($questions as $question) {
            if (isset($question['options'])) {
                $correct = self::correct($student, $question['type'], $record);
                if ($correct === null || ! in_array(StudentImportColumns::fold($correct), array_map(StudentImportColumns::fold(...), $question['options']), true)) {
                    return false;
                }
            } elseif (! self::proofAvailable($student, $question['type'])) {
                return false;
            }
        }

        return $questions !== [];
    }

    /** @return array{student_id:int,questions:list<array<string,mixed>>,failures:int,expires_at:int}|null */
    private static function challenge(User $user): ?array
    {
        $stored = Cache::get(self::key($user));
        if (! is_array($stored) || ! is_int($stored['student_id'] ?? null) || ! is_int($stored['failures'] ?? null) || ! is_int($stored['expires_at'] ?? null)
            || ! is_array($stored['questions'] ?? null) || $stored['expires_at'] <= now()->getTimestamp()) {
            return null;
        }
        $questions = [];
        foreach ($stored['questions'] as $question) {
            if (! is_array($question) || ! is_string($question['type'] ?? null)) {
                return null;
            }
            $questions[] = $question;
        }

        return ['student_id' => $stored['student_id'], 'questions' => $questions, 'failures' => $stored['failures'], 'expires_at' => $stored['expires_at']];
    }

    /** @param array{student_id:int,questions:list<array<string,mixed>>,failures:int,expires_at:int} $challenge */
    private static function store(User $user, array $challenge): void
    {
        Cache::put(self::key($user), $challenge, Carbon::createFromTimestamp($challenge['expires_at']));
    }

    private static function key(User $user): string
    {
        return 'student-identity-questions:'.$user->id;
    }
}
