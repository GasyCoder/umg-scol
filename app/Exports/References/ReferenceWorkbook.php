<?php

namespace App\Exports\References;

use App\Models\User;
use App\Support\References\ReferenceTransfer;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Model (empty data sheet) or export (filtered list) of mentions/programs, followed by the "Codes" help sheet. */
class ReferenceWorkbook implements Export, WithMultipleSheets
{
    /** @param  list<list<string>>  $rows */
    public function __construct(private string $kind, private User $user, private array $rows = []) {}

    /** @return list<ReferenceSheet> */
    public function sheets(): array
    {
        $codes = ReferenceTransfer::codes($this->kind, $this->user);

        return [
            new ReferenceSheet(ReferenceTransfer::title($this->kind), array_values(ReferenceTransfer::columns($this->kind)), $this->rows),
            new ReferenceSheet('Codes', $codes['headings'], $codes['rows']),
        ];
    }
}
