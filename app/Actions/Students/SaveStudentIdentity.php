<?php

namespace App\Actions\Students;

use App\Models\Student;
use App\Models\User;
use App\Support\StudentIdentityLocks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveStudentIdentity
{
    /**
     * @param  array<string,mixed>  $data
     * @param  string  $ability  Policy ability checked on an existing student (updateAtReception from the reception counter).
     */
    public function handle(User $actor, ?Student $student, array $data, string $ability = 'update'): Student
    {
        return Cache::lock('students:manual-identity-write', 60)->block(10, fn (): Student => $this->persist($actor, $student, $data, $ability));
    }

    /** @param array<string,mixed> $data */
    public function persist(User $actor, ?Student $student, array $data, string $ability = 'update'): Student
    {
        return DB::transaction(function () use ($actor, $student, $data, $ability): Student {
            $target = $student === null ? new Student : Student::query()->lockForUpdate()->findOrFail($student->id);
            Gate::forUser($actor)->authorize($student === null ? 'create' : $ability, $student === null ? Student::class : $target);
            $target->fill($data);
            StudentIdentityLocks::acquire(StudentIdentityLocks::keys($target->attributesToArray()));
            foreach (StudentIdentityLocks::uniqueValues($target->attributesToArray()) as $field => $value) {
                if (Student::query()->when($target->exists, fn ($q) => $q->where('id', '!=', $target->id))->where($field, $value)->exists()) {
                    throw ValidationException::withMessages([$field => match ($field) {
                        'phone' => 'Ce numéro de téléphone est déjà utilisé par un autre étudiant.',
                        'email' => 'Cette adresse mail est déjà utilisée par un autre étudiant.',
                        default => 'Une identité correspondante existe déjà. Vérifiez les données ou contactez la Scolarité Centrale.',
                    }]);
                }
            }
            if ($target->birth_date !== null && Student::query()->when($target->exists, fn ($q) => $q->where('id', '!=', $target->id))
                ->where('last_name', $target->last_name)->where('first_names', $target->first_names)->whereDate('birth_date', $target->birth_date)->exists()) {
                throw ValidationException::withMessages(['last_name' => 'Une identité de mêmes nom, prénoms et naissance existe déjà. Vérifiez les données ou contactez la Scolarité Centrale.']);
            }
            if ($target->birth_date !== null && $target->cin_issued_at !== null && $target->cin_issued_at->lessThan($target->birth_date)) {
                throw ValidationException::withMessages(['cin_issued_at' => 'La délivrance de la CIN ne peut pas précéder la naissance.']);
            }
            $target->save();

            return $target;
        }, 5);
    }
}
