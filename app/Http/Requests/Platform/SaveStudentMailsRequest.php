<?php

namespace App\Http\Requests\Platform;

use App\Support\StudentMails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveStudentMailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('branding.manage-ui');
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return collect(StudentMails::KINDS)->mapWithKeys(fn ($label, string $key) => [$key => ['required', 'boolean']])->all();
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['required' => 'Choix manquant.', 'boolean' => 'Choix invalide.'];
    }
}
