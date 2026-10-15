<?php

namespace App\Enums;

/** "Institution" column of the establishment file. */
enum EstablishmentSector: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publique',
            self::Private => 'Privée',
        };
    }
}
