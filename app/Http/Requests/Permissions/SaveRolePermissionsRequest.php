<?php

namespace App\Http\Requests\Permissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('permissions.manage-ui');
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'distinct', Rule::in(config('access.permissions'))], 'version' => ['required', 'string', 'size:64']];
    }

    public function messages(): array
    {
        return ['permissions.*.in' => 'Cette permission ne fait pas partie du catalogue.', 'version.required' => 'Actualisez la matrice avant de modifier les permissions.'];
    }
}
