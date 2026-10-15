<?php

namespace App\Http\Requests\References;

use App\Models\Domain;
use Illuminate\Validation\Rule;

class SaveDomainRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Domain::class;
    }

    protected function parameterName(): string
    {
        return 'domain';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('domain');
        $id = $record instanceof Domain ? $record->id : null;

        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('domains')->ignore($id)], 'name' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'], 'is_active' => ['sometimes', 'boolean']];
    }
}
