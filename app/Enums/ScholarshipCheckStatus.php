<?php

namespace App\Enums;

enum ScholarshipCheckStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Issue = 'issue';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À vérifier',
            self::Ok => 'Conforme',
            self::Issue => 'Anomalie',
            self::NotApplicable => 'Non applicable',
        };
    }

    public function allowsValidation(): bool
    {
        return $this === self::Ok || $this === self::NotApplicable;
    }
}
