<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Establishment;
use App\Models\Level;
use App\Models\Program;
use App\Models\User;

class StudentReferenceOptions
{
    /** @return array<string,mixed> */
    public function forUser(User $user): array
    {
        return [
            'establishments' => Establishment::query()->when($user->hasRole('establishment'), fn ($q) => $q->whereKey($user->establishment_id))->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'academicYears' => AcademicYear::query()->orderByDesc('is_current')->orderByDesc('name')->get(['id', 'name', 'is_active', 'is_current']),
            'levels' => Level::query()->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            // Mention included so that the forms can group programs by mention.
            'programs' => Program::query()->with('mention:id,name')->when($user->hasRole('establishment'), fn ($q) => $q->where('establishment_id', $user->establishment_id))->orderBy('name')->get(['id', 'establishment_id', 'mention_id', 'code', 'name', 'is_active']),
            'fixedEstablishmentId' => $user->hasRole('establishment') ? $user->establishment_id : null,
        ];
    }
}
