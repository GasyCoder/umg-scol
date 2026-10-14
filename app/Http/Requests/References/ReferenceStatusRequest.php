<?php

namespace App\Http\Requests\References;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReferenceStatusRequest extends FormRequest
{
    use HasReferenceMessages;

    public function authorize(): bool
    {
        foreach ($this->route()->parameters() as $model) {
            if ($model instanceof Model) {
                return Gate::allows('update', $model);
            }
        }

        return false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
