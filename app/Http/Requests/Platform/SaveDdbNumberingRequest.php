<?php

namespace App\Http\Requests\Platform;

use App\Support\Scholarships\DdbNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveDdbNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('branding.manage-ui');
    }

    protected function prepareForValidation(): void
    {
        $pattern = $this->input('ddb_pattern');
        $this->merge(['ddb_pattern' => is_string($pattern) ? mb_strtoupper(trim($pattern)) : $pattern]);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            // Letters, digits, separators and the known tokens; {NUM} keeps each number unique in its academic year.
            'ddb_pattern' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9{}\/._ -]+$/', function (string $attribute, mixed $value, Closure $fail): void {
                $rest = str_replace(array_keys(DdbNumber::TOKENS), '', (string) $value);
                if (! str_contains((string) $value, '{NUM}')) {
                    $fail('Le modèle doit contenir {NUM}, le numéro d’ordre dans l’année universitaire.');
                } elseif (str_contains($rest, '{') || str_contains($rest, '}')) {
                    $fail('Le modèle contient une balise inconnue. Balises possibles : '.implode(', ', array_keys(DdbNumber::TOKENS)).'.');
                }
            }],
            'ddb_digits' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['ddb_pattern.required' => 'Le modèle du numéro DDB est obligatoire.', 'ddb_pattern.max' => 'Le modèle dépasse 60 caractères.', 'ddb_pattern.regex' => 'Le modèle ne peut contenir que des lettres, des chiffres, les séparateurs - _ / . et les balises.', 'ddb_digits.*' => 'Le nombre de chiffres doit être compris entre 1 et 10.'];
    }
}
