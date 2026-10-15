<?php

namespace App\Enums;

/** "Type de formation" of a program. */
enum TrainingType: string
{
    case Academic = 'academic';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Académique',
            self::Professional => 'Professionnalisant',
        };
    }
}
