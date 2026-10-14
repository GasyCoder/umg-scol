<?php

namespace App\Enums;

enum ScholarshipApplicationStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Validated = 'validated';
    case NeedsCorrection = 'needs_correction';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Reçu',
            self::InReview => 'En vérification',
            self::Validated => 'Validé',
            self::NeedsCorrection => 'Correction demandée',
            self::Rejected => 'Rejeté',
        };
    }

    /** @return list<self> */
    public function targets(): array
    {
        return match ($this) {
            self::Pending => [self::InReview, self::Validated, self::NeedsCorrection, self::Rejected],
            self::InReview => [self::Validated, self::NeedsCorrection, self::Rejected],
            self::NeedsCorrection => [self::InReview, self::Validated, self::Rejected],
            self::Validated, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->targets(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Validated || $this === self::Rejected;
    }

    /** Permission required to move a file to this status. */
    public function permission(): string
    {
        return match ($this) {
            self::InReview => 'scholarships.review',
            self::Validated => 'scholarships.validate',
            self::NeedsCorrection => 'scholarships.request_correction',
            self::Rejected => 'scholarships.reject',
            self::Pending => 'scholarships.receive',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::NeedsCorrection || $this === self::Rejected;
    }
}
