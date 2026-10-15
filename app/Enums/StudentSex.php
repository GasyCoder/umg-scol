<?php

namespace App\Enums;

enum StudentSex: string
{
    case Male = 'M';
    case Female = 'F';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Masculin',
            self::Female => 'Féminin',
        };
    }
}
