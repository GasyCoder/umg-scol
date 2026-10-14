<?php

namespace App\Imports\References;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Reads only the first worksheet (the "Codes" help sheet of the model is ignored). */
class ReferenceSheetReader implements Import, SkipsUnknownSheets, WithCustomCsvSettings, WithMultipleSheets
{
    public function __construct(private string $delimiter = ',') {}

    /** @return array<int, self> */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /** @param  int|string  $sheetName */
    public function onUnknownSheet($sheetName): void {}

    /** @return array<string, mixed> */
    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->delimiter, 'input_encoding' => 'UTF-8'];
    }
}
