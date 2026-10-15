<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePlatformBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('branding.manage-ui');
    }

    protected function prepareForValidation(): void
    {
        $input = [];
        foreach (['institution_name', 'scol_name', 'bourse_name', 'support_email'] as $key) {
            $value = $this->input($key);
            $input[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        $this->merge($input);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'institution_name' => ['nullable', 'string', 'max:120'],
            'scol_name' => ['nullable', 'string', 'max:60'],
            'bourse_name' => ['nullable', 'string', 'max:60'],
            'support_email' => ['nullable', 'email', 'max:255'],
            // Raster images only: an SVG served to every visitor could carry scripts.
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000'],
            'favicon' => ['nullable', 'file', 'image', 'mimes:png', 'max:256', 'dimensions:ratio=1,min_width=32,max_width=1024'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'logo.image' => 'Le logo doit être une image.', 'logo.mimes' => 'Le logo doit être au format PNG, JPG ou WebP.', 'logo.max' => 'Le logo ne doit pas dépasser 1 Mo.',
            'logo.dimensions' => 'Le logo doit mesurer entre 32 et 4000 pixels de côté.',
            'favicon.image' => 'Le favicon doit être une image.', 'favicon.mimes' => 'Le favicon doit être une image PNG.', 'favicon.max' => 'Le favicon ne doit pas dépasser 256 Ko.',
            'favicon.dimensions' => 'Le favicon doit être carré, entre 32 et 1024 pixels.',
            'support_email.email' => 'L’adresse email de contact est invalide.',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['institution_name' => 'nom de l’institution', 'scol_name' => 'nom du portail interne', 'bourse_name' => 'nom du portail étudiant', 'support_email' => 'email de contact'];
    }
}
