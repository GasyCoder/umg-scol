<?php

namespace App\Enums;

enum StudentRecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    /** Entered by the central office: active only once validated (establishment list or PNES base). */
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Pending => 'En attente',
        };
    }
}
