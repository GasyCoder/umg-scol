<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** The verified address must identify exactly one active official student record. */
final class StudentPortalIdentity
{
    /** @return Builder<Student> */
    public static function query(string $email): Builder
    {
        return Student::query()->where('is_active', true)->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))]);
    }

    public static function find(string $email, bool $lock = false): ?Student
    {
        $query = self::query($email);
        if ($lock) {
            $query->lockForUpdate();
        }
        $students = $query->limit(2)->get();

        return $students->count() === 1 ? $students->first() : null;
    }

    public static function forAccount(User $user, bool $lock = false): ?Student
    {
        $student = self::find($user->email, $lock);

        return $student !== null && ($user->student_id === null || $user->student_id === $student->id) ? $student : null;
    }
}
