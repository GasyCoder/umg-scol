<?php

namespace App\Enums;

/** The three files the platform imports into student situations. */
enum ImportKind: string
{
    case Establishment = 'establishment';
    case Pnes = 'pnes';
    case Preinscription = 'preinscription';

    public function label(): string
    {
        return match ($this) {
            self::Establishment => 'Liste d’un établissement',
            self::Pnes => 'Base PNES des boursiers',
            self::Preinscription => 'Résultats de préinscription',
        };
    }

    /** Permission needed to upload, analyse and confirm this kind of file. */
    public function permission(): string
    {
        return match ($this) {
            self::Establishment => 'imports.create',
            self::Pnes => 'imports.pnes',
            self::Preinscription => 'imports.preinscription',
        };
    }

    public function source(): SituationSource
    {
        return match ($this) {
            self::Establishment => SituationSource::Establishment,
            self::Pnes => SituationSource::Pnes,
            self::Preinscription => SituationSource::Preinscription,
        };
    }
}
