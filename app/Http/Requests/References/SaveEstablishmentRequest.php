<?php

namespace App\Http\Requests\References;

use App\Enums\EstablishmentSector;
use App\Models\Establishment;
use Illuminate\Validation\Rule;

class SaveEstablishmentRequest extends ReferenceRequest
{
    protected function modelClass(): string
    {
        return Establishment::class;
    }

    protected function parameterName(): string
    {
        return 'establishment';
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $record = $this->route('establishment');
        $id = $record instanceof Establishment ? $record->id : null;

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('establishments')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            // Faculté, Institut, École or a free "Autre" type: stored as text either way.
            'type' => ['nullable', 'string', 'max:100'],
            // "Institution" column of the establishment file.
            'sector' => ['nullable', Rule::enum(EstablishmentSector::class)],
            'address' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            // Raster images only: an SVG could carry scripts.
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'regex' => 'Le sigle doit contenir uniquement des majuscules, chiffres, points, tirets ou underscores.',
            'logo.image' => 'Le logo doit être une image.', 'logo.mimes' => 'Le logo doit être au format PNG, JPG ou WebP.',
            'logo.max' => 'Le logo ne doit pas dépasser 1 Mo.', 'logo.dimensions' => 'Le logo doit mesurer entre 32 et 4000 pixels de côté.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...parent::attributes(), 'code' => 'sigle', 'type' => 'type', 'logo' => 'logo'];
    }
}
