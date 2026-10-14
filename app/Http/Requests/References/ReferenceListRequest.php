<?php

namespace App\Http\Requests\References;

use Illuminate\Foundation\Http\FormRequest;

class ReferenceListRequest extends FormRequest
{
    use HasReferenceMessages;

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'establishment_id' => ['nullable', 'integer', 'exists:establishments,id']];
    }
}
