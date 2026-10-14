<?php

namespace App\Http\Requests\References;

trait HasReferenceMessages
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'unique' => 'Cette valeur de :attribute est déjà utilisée.',
            'exists' => 'L’établissement sélectionné n’existe pas.',
            'regex' => 'Le code doit contenir uniquement lettres, chiffres, points, tirets ou underscores.',
            'max' => 'Le champ :attribute dépasse la limite autorisée (:max).',
            'min' => 'Le champ :attribute doit être supérieur ou égal à :min.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'boolean' => 'Le statut indiqué est invalide.',
            'string' => 'Le champ :attribute doit être du texte.',
            'email' => 'L’adresse email est invalide.',
            'date_format' => 'La date doit respecter le format année-mois-jour.',
            'after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nom', 'code' => 'code', 'sort_order' => 'ordre', 'establishment_id' => 'établissement', 'search' => 'recherche'];
    }
}
