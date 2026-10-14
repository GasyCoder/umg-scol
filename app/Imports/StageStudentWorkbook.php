<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StageStudentWorkbook implements Import, WithChunkReading, WithMultipleSheets
{
    public function __construct(private StageStudentSheet $sheet) {}

    public function sheets(): array
    {
        return [0 => $this->sheet];
    }

    public function chunkSize(): int
    {
        return $this->sheet->chunkSize();
    }
}
