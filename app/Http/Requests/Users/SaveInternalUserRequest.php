<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Support\Users\InternalRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveInternalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User ? Gate::allows('update', $user) : Gate::allows('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null)],
            'role' => ['required', 'string', Rule::in(InternalRoles::assignableBy($this->user()))],
            'establishment_id' => [Rule::requiredIf($this->input('role') === 'establishment'), 'nullable', 'integer', Rule::exists('establishments', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte utilise déjà cette adresse email.',
            'role.in' => 'Choisissez un rôle disponible.',
            'establishment_id.required' => 'Un compte établissement doit être rattaché à un établissement.',
            'establishment_id.exists' => 'Choisissez un établissement actif.',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['name' => 'nom complet', 'email' => 'adresse email', 'role' => 'rôle', 'establishment_id' => 'établissement'];
    }

    /** @return array{name:string,email:string,role:string,establishment_id:int|null} */
    public function payload(): array
    {
        return ['name' => $this->validated('name'), 'email' => $this->validated('email'), 'role' => $this->validated('role'), 'establishment_id' => $this->validated('establishment_id') === null ? null : (int) $this->validated('establishment_id')];
    }
}
