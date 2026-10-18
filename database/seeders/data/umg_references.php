<?php

/*
 * Institutional referential of the UMG, seeded by UmgReferenceSeeder (no personal data).
 * Source: establishment codes, names, mentions and programs of the national PNES base and of the
 * establishment template. Names given as acronyms and missing domains are completed by the Scolarité
 * in Référentiels; the seeder never overwrites what was edited there.
 */

return [
    'domains' => [
        'ALSH' => 'Arts, Lettres et Sciences Humaines',
    ],
    // code => name, aliases (other codes of the same establishment), mentions => [domain, programs]
    'establishments' => [
        'EATP' => [
            'name' => 'EATP',
            'mentions' => [
                'Arts et Techniques en prothèse dentaire' => ['domain' => null, 'programs' => ['Arts et Techniques en prothèse dentaire']],
            ],
        ],
        'EDEN' => [
            'name' => 'EDEN',
            'mentions' => [
                'Ecosystèmes Naturels' => ['domain' => null, 'programs' => ['Ecosystèmes Naturels']],
            ],
        ],
        'EDGVM' => [
            'name' => 'EDGVM',
            'mentions' => [
                'Génie du Vivant et Modelisation' => ['domain' => null, 'programs' => ['Génie du Vivant et Modelisation']],
            ],
        ],
        'EDSPA' => [
            'name' => 'Ecole de Droit et Science Politique Antsohihy',
            'aliases' => ['EDSP'],
            'mentions' => [
                'Droit' => ['domain' => 'ALSH', 'programs' => ['Tronc Commun', 'Droit Privé', 'Droit']],
            ],
        ],
        'EDSPM' => [
            'name' => 'EDSP Mahajanga',
            'mentions' => [
                'Droit' => ['domain' => 'ALSH', 'programs' => ['Droit']],
                'Science Politique' => ['domain' => null, 'programs' => ['Science Politique']],
            ],
        ],
        'ELCI' => [
            'name' => 'ELCI',
            'mentions' => [
                'Langues Appliquées' => ['domain' => null, 'programs' => ['Langues Appliquées']],
                'Langue et Culture Chinoises' => ['domain' => null, 'programs' => ['Langue et Culture Chinoises']],
            ],
        ],
        'ENSU' => [
            'name' => 'ENS-UMG',
            'mentions' => [
                'EADSNPM' => ['domain' => null, 'programs' => ['EADSNPM']],
                'EADLV' => ['domain' => null, 'programs' => ['EADLV']],
                'EADSS' => ['domain' => null, 'programs' => ['EADSS']],
            ],
        ],
        'EP' => [
            'name' => 'Ecole de Pharmacie',
            'mentions' => [
                'Pharmacie' => ['domain' => null, 'programs' => ['Pharmacie']],
            ],
        ],
        'ET' => [
            'name' => 'Ecole de Tourisme',
            'mentions' => [
                'Tourisme' => ['domain' => null, 'programs' => ['Tourisme']],
            ],
        ],
        'EV' => [
            'name' => 'Ecole Vétérinaire',
            'mentions' => [
                'Médecine vétérinaire' => ['domain' => null, 'programs' => ['Médecine vétérinaire']],
            ],
        ],
        'FACMED' => [
            'name' => 'Faculté de Médecine',
            'mentions' => [
                'Médecine Humaine' => ['domain' => null, 'programs' => ['Médecine Humaine']],
            ],
        ],
        'FSTE' => [
            'name' => 'FSTE',
            'mentions' => [
                'STE' => ['domain' => null, 'programs' => ['STE']],
                'SVE' => ['domain' => null, 'programs' => ['SVE']],
                'BSE' => ['domain' => null, 'programs' => ['BSE']],
                'SMS' => ['domain' => null, 'programs' => ['SMS']],
                'CFoAp' => ['domain' => null, 'programs' => ['CFoAp']],
                'SNE' => ['domain' => null, 'programs' => ['SNE']],
            ],
        ],
        'ILCSS' => [
            'name' => 'ILCSS',
            'mentions' => [
                'Etudes Françaises' => ['domain' => null, 'programs' => ['Etudes Françaises']],
                'Communication' => ['domain' => null, 'programs' => ['Communication']],
                'Psychologie' => ['domain' => null, 'programs' => ['Psychologie']],
                'Philosophie' => ['domain' => null, 'programs' => ['Philosophie']],
                'Sociologie' => ['domain' => null, 'programs' => ['Sociologie']],
                'Anthropologie' => ['domain' => null, 'programs' => ['Anthropologie']],
            ],
        ],
        'IOSTM' => [
            'name' => 'IOSTM',
            'mentions' => [
                'Santé Publique' => ['domain' => null, 'programs' => ['Santé Publique']],
                'Assistanat Dentaire' => ['domain' => null, 'programs' => ['Assistanat Dentaire']],
                'Diététique' => ['domain' => null, 'programs' => ['Diététique']],
            ],
        ],
        'ISSTM' => [
            'name' => 'ISSTM',
            'mentions' => [
                'STNPA' => ['domain' => null, 'programs' => ['STNPA']],
                'STI' => ['domain' => null, 'programs' => ['STI']],
                'STGC' => ['domain' => null, 'programs' => ['STGC']],
            ],
        ],
        'IUGMANT' => [
            'name' => 'IUGM Antsohihy',
            'mentions' => [
                'Gestion et Commerce' => ['domain' => null, 'programs' => ['Gestion et Commerce']],
            ],
        ],
        'IUGMMAE' => [
            'name' => 'IUGM - Maevatanana',
            'mentions' => [
                'Gestion et Commerce' => ['domain' => null, 'programs' => ['Gestion et Commerce']],
            ],
        ],
        'IUGMMAH' => [
            'name' => 'IUGM Mahajanga',
            'mentions' => [
                'Finance' => ['domain' => null, 'programs' => ['Finance']],
                'Economie' => ['domain' => null, 'programs' => ['Economie']],
                'Gestion' => ['domain' => null, 'programs' => ['Gestion']],
                'Commerce' => ['domain' => null, 'programs' => ['Commerce']],
                'Management' => ['domain' => null, 'programs' => ['Management']],
                'Marketing' => ['domain' => null, 'programs' => ['Marketing']],
            ],
        ],
        'IUTAMANT' => [
            'name' => 'IUTAM Antsohihy',
            'mentions' => [
                'Biologie Appliquée' => ['domain' => null, 'programs' => ['Biologie Appliquée']],
            ],
        ],
        'IUTAMMAE' => [
            'name' => 'IUTAM Maevatanana',
            'mentions' => [
                'Biologie Appliquée' => ['domain' => null, 'programs' => ['Biologie Appliquée']],
            ],
        ],
        'IUTAMMAH' => [
            'name' => 'IUTAM Mahajanga',
            'mentions' => [
                'Biologie Appliquée' => ['domain' => null, 'programs' => ['Biologie Appliquée']],
                'Agrobiodiversité et Environnement' => ['domain' => null, 'programs' => ['Agrobiodiversité et Environnement']],
            ],
        ],
        'UNIV' => [
            'name' => 'UNIVERSITE DE L\'ANDRONA',
            'mentions' => [
                'Sciences Agronomiques et de Développement Durable' => ['domain' => null, 'programs' => ['Sciences Agronomiques et de Développement Durable']],
                'Sciences Humaines' => ['domain' => null, 'programs' => ['Sciences Humaines']],
                'Agroécologie et Développement Durable' => ['domain' => null, 'programs' => ['Agroécologie et Développement Durable']],
            ],
        ],
    ],
];
