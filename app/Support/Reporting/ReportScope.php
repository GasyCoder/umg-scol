<?php

namespace App\Support\Reporting;

use App\Models\AcademicYear;
use App\Models\User;

/**
 * Perimeter of every dashboard and report figure, resolved server-side:
 * an establishment account is always limited to its own establishment; global actors see everything.
 * The academic year defaults to the current active year, never to the latest row.
 */
final class ReportScope
{
    private function __construct(
        public readonly User $user,
        public readonly ?int $establishmentId,
        public readonly ?AcademicYear $year,
        public readonly ?AcademicYear $currentYear,
    ) {}

    /** $yearId is honoured only when it names an existing year; otherwise the current year applies. */
    public static function for(User $user, ?int $yearId = null): self
    {
        $current = AcademicYear::query()->where('is_current', true)->where('is_active', true)->first(['id', 'name', 'is_current', 'is_active']);
        $year = $yearId !== null && $yearId !== $current?->id ? AcademicYear::query()->find($yearId, ['id', 'name', 'is_current', 'is_active']) : $current;

        return new self($user, $user->hasRole('establishment') ? (int) $user->establishment_id : null, $year, $current);
    }

    public function isGlobal(): bool
    {
        return $this->establishmentId === null;
    }

    public function isCurrentYear(): bool
    {
        return $this->year !== null && $this->year->id === $this->currentYear?->id;
    }

    /** @return array{id:int,name:string,is_current:bool}|null */
    public function yearSummary(): ?array
    {
        return $this->year === null ? null : ['id' => $this->year->id, 'name' => $this->year->name, 'is_current' => $this->isCurrentYear()];
    }
}
