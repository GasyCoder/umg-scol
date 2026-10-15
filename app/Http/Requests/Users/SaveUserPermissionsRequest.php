<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('managePermissions', $this->route('user'));
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        $catalogue = Rule::in(config('access.permissions'));

        return [
            'granted' => ['present', 'array'], 'granted.*' => ['string', 'distinct', $catalogue],
            'denied' => ['present', 'array'], 'denied.*' => ['string', 'distinct', $catalogue, 'not_in:'.implode(',', array_filter((array) $this->input('granted', []), 'is_string'))],
            'version' => ['required', 'string', 'size:64'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['granted.*.in' => 'Cette permission ne fait pas partie du catalogue.', 'denied.*.in' => 'Cette permission ne fait pas partie du catalogue.', 'denied.*.not_in' => 'Un droit ne peut pas être à la fois ajouté et retiré.', 'version.required' => 'Actualisez la page avant de modifier les droits.'];
    }
}
