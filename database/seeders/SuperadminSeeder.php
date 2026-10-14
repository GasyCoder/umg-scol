<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $input = config()->array('provisioning.superadmin');

        if (collect($input)->every(fn ($value): bool => blank($value))) {
            $this->command->warn('Provisionnement superadmin ignoré : variables SUPERADMIN_* absentes.');

            return;
        }

        // Une erreur de configuration ne doit jamais afficher la valeur du secret.
        if (Validator::make($input, ['email' => ['required', 'string', 'email', 'max:255']])->fails()) {
            throw new RuntimeException('SUPERADMIN_EMAIL doit être une adresse valide.');
        }

        DB::transaction(function () use ($input): void {
            $user = User::query()->where('email', $input['email'])->lockForUpdate()->first();

            if ($user === null) {
                $validator = Validator::make($input, [
                    'name' => ['required', 'string', 'max:255'],
                    'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
                ]);

                if ($validator->fails()) {
                    throw new RuntimeException('SUPERADMIN_NAME et un mot de passe de 12 caractères minimum (majuscules, minuscules, chiffres et symboles) sont requis.');
                }

                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);
            }

            if (! $user->hasRole('superadmin', config('access.guard'))) {
                $user->assignRole('superadmin');
            }
        });
    }
}
