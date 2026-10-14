<?php

namespace App\Http\Requests\References;

use App\Models\Level;
use Illuminate\Validation\Rule;

class SaveLevelRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Level::class;
    }

    protected function parameterName(): string
    {
        return 'level';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('level');
        $id = $record instanceof Level ? $record->id : null;

        return ['code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('levels')->ignore($id)], 'name' => ['required', 'string', 'max:255'], 'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'], 'is_active' => ['sometimes', 'boolean']];
    }
}
