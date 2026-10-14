<?php

return [
    /*
     * Generic verification checklist of the paper file against the official record.
     * Not a regulatory list of supporting documents: adjust once the Scolarité Centrale
     * provides its official checklist. Keys are stored per file at reception time.
     */
    'checks' => [
        'identity' => 'Identité conforme à la fiche officielle',
        'enrollment' => 'Déclaration annuelle conforme (établissement, niveau, parcours)',
        'bac' => 'Informations BAC conformes',
        'contact' => 'Coordonnées vérifiées',
        'complete' => 'Dossier papier complet',
    ],

    // Technical file number: {prefix}{campaign id}-{6-digit sequence per campaign}, e.g. BRS3-000042.
    'number_prefix' => 'BRS',
];
