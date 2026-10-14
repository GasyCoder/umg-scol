<?php

namespace App\Http\Requests\References;

use App\Models\Establishment;
use Illuminate\Validation\Rule;

class SaveEstablishmentRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Establishment::class;
    }

    protected function parameterName(): string
    {
        return 'establishment';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('establishment');
        $id = $record instanceof Establishment ? $record->id : null;

        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('establishments')->ignore($id)], 'name' => ['required', 'string', 'max:255'], 'type' => ['nullable', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'is_active' => ['sometimes', 'boolean']];
    }
}
