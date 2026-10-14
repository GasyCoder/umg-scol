<?php

namespace App\Http\Requests\StudentPortal;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the opaque token of the displayed version: no Student value, ID or year is accepted.
     *
     * @return array<string,array<mixed>>
     */
    public function rules(): array
    {
        return ['version' => ['required', 'string', 'size:64']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['version.required' => 'Rechargez vos informations avant de confirmer.', 'version.size' => 'Rechargez vos informations avant de confirmer.'];
    }
}
