<?php

return [
    /*
     * Generic verification checklist of the paper file against the official record.
     * Not a regulatory list of supporting documents: adjust once the Scolarité Centrale
     * provides its official checklist. Keys are stored per file at reception time.
     */
    'checks' => [
        'identity' => 'Identité conforme à la fiche officielle',
        'enrollment' => 'Situation de l’année conforme (établissement, niveau, parcours)',
        'bac' => 'Informations BAC conformes',
        'contact' => 'Coordonnées vérifiées',
        'complete' => 'Dossier papier complet',
    ],

    // Technical file number, never shown: {prefix}-{academic year}-{6-digit sequence of the year}, e.g. BRS-2026-2027-000042.
    'number_prefix' => 'BRS',

    // DDB label sheet: link of the QR code printed on each label (student portal), and the most sheets printed at once.
    'label_url' => env('SCHOLARSHIP_LABEL_URL', 'https://bourse.mahajanga-univ.mg'),
    'label_sheets_max' => 20,
];
