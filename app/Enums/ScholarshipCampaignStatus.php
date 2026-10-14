<?php

namespace App\Enums;

enum ScholarshipCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Scheduled => 'Programmée',
            self::Open => 'Ouverte',
            self::Closed => 'Clôturée',
            self::Archived => 'Archivée',
        };
    }

    /** @return list<self> */
    public function targets(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Open],
            self::Scheduled => [self::Open],
            self::Open => [self::Closed],
            self::Closed => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->targets(), true);
    }
}
