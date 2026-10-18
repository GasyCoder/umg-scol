<?php

namespace App\Enums;

/** Where the situation of the year comes from. */
enum SituationSource: string
{
    case Establishment = 'establishment';
    case Pnes = 'pnes';
    case Central = 'central';
    case Preinscription = 'preinscription';

    public function label(): string
    {
        return match ($this) {
            self::Establishment => 'Liste de l’établissement',
            self::Pnes => 'Base PNES (boursiers)',
            self::Central => 'Saisie de la Scolarité',
            self::Preinscription => 'Préinscription (nouveau bachelier)',
        };
    }
}
