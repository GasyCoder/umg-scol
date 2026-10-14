<?php

namespace App\Actions\References;

use App\Models\Establishment;
use App\Models\Mention;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveInstitutionalChild
{
    /** @param array<string,mixed> $data */
    public function handle(User $actor, Mention|Program $target, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $data) {
            $class = $target::class;
            if ($target->exists) {
                $target = $class::query()->lockForUpdate()->findOrFail($target->id);
            }
            Gate::forUser($actor)->authorize($target->exists ? 'update' : 'create', $target->exists ? $target : $class);
            $data['establishment_id'] = $data['establishment_id'] ?? $target->getAttribute('establishment_id');
            abort_if($actor->hasRole('establishment') && (int) $data['establishment_id'] !== $actor->establishment_id, 403);
            if (Establishment::query()->whereKey($data['establishment_id'])->sharedLock()->first() === null) {
                throw ValidationException::withMessages(['establishment_id' => 'Cet établissement n’est plus disponible.']);
            }
            if ($target instanceof Program && isset($data['mention_id']) && Mention::query()->whereKey($data['mention_id'])->where('establishment_id', $data['establishment_id'])->sharedLock()->first() === null) {
                throw ValidationException::withMessages(['mention_id' => 'Cette mention n’est plus disponible dans cet établissement.']);
            }
            $used = $target instanceof Program ? $target->studentRecords()->withTrashed()->exists() : $target->programs()->withTrashed()->exists();
            if ($target->exists && $target->establishment_id !== (int) $data['establishment_id'] && $used) {
                throw ValidationException::withMessages(['establishment_id' => 'Un référentiel utilisé ne peut pas changer d’établissement.']);
            }
            $target->fill($data)->save();
        }, 5);
    }
}
