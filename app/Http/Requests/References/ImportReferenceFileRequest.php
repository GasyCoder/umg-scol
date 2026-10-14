<?php

namespace App\Http\Requests\References;

use App\Support\References\ReferenceTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportReferenceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ReferenceTransfer::model((string) $this->route('kind')));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt', 'max:'.ReferenceTransfer::MAX_KILOBYTES]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Sélectionnez un fichier à importer.',
            'file.file' => 'Le fichier n’a pas pu être envoyé.',
            'file.extensions' => 'Seules les extensions .xlsx et .csv sont acceptées.',
            'file.mimes' => 'Seuls les fichiers XLSX et CSV sont acceptés.',
            'file.max' => 'Le fichier dépasse la taille maximale autorisée (2 Mo).',
        ];
    }
}
