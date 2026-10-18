<?php

namespace App\Http\Requests\Platform;

use App\Support\Scholarships\DdbLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveDdbLabelTextsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('branding.manage-ui');
    }

    protected function prepareForValidation(): void
    {
        $input = [];
        foreach (DdbLabel::keys() as $key) {
            $value = $this->input($key);
            $input[$key] = is_string($value) && trim($value) !== '' ? preg_replace('/\s+/u', ' ', trim($value)) : null;
        }
        $this->merge($input);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        // Short texts: a label is 99 × 57 mm.
        return [
            'ddb_label_heading' => ['nullable', 'string', 'max:40'],
            'ddb_label_caption' => ['nullable', 'string', 'max:30'],
            'ddb_label_hint' => ['nullable', 'string', 'max:50'],
            'ddb_label_footer' => ['nullable', 'string', 'max:60'],
            DdbLabel::URL_KEY => ['nullable', 'string', 'max:100', 'url:http,https'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'ddb_label_heading.max' => 'La ligne sous l’institution dépasse 40 caractères.',
            'ddb_label_caption.max' => 'Le titre du numéro dépasse 30 caractères.',
            'ddb_label_hint.max' => 'La mention sous le numéro dépasse 50 caractères.',
            'ddb_label_footer.max' => 'Le pied de l’étiquette dépasse 60 caractères.',
            'ddb_label_url.max' => 'Le lien du QR code dépasse 100 caractères.',
            'ddb_label_url.url' => 'Le lien du QR code doit être une adresse complète, par exemple https://bourse.mahajanga-univ.mg.',
        ];
    }
}
