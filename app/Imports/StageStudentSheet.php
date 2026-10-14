<?php

namespace App\Imports;

use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Support\StudentImportColumns;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StageStudentSheet implements Import, OnEachRow, WithChunkReading
{
    /** @var list<array<string,mixed>> */
    private array $pending = [];

    private int $count = 0;

    public function __construct(private StudentImport $import) {}

    public function chunkSize(): int
    {
        return (int) config('student_imports.chunk_size');
    }

    public function onRow(Row $row): void
    {
        $raw = array_values($row->toArray(null, false, false));
        if (count($raw) > (int) config('student_imports.max_columns')) {
            throw ValidationException::withMessages(['file' => 'Le fichier dépasse le nombre maximal de colonnes.']);
        }
        if ($row->getIndex() === 1) {
            $headers = array_map(fn ($v) => (string) $v, $raw);
            if ($headers === [] || count(array_filter($headers)) === 0) {
                throw ValidationException::withMessages(['file' => 'Les en-têtes sont absents.']);
            }
            $this->import->update(['excel_calendar' => pathinfo($this->import->stored_path, PATHINFO_EXTENSION) === 'csv' ? 1900 : Date::getExcelCalendar(), 'headers' => $headers, 'mapping' => StudentImportColumns::suggest($headers)]);

            return;
        }
        if (count(array_filter($raw, fn ($v) => $v !== null && $v !== '')) === 0) {
            return;
        }
        if (++$this->count > (int) config('student_imports.max_rows')) {
            throw ValidationException::withMessages(['file' => 'Le fichier dépasse le nombre maximal de lignes.']);
        }
        $this->pending[] = ['student_import_id' => $this->import->id, 'row_number' => $row->getIndex(), 'raw_data' => json_encode($raw, JSON_THROW_ON_ERROR), 'status' => 'rejected', 'created_at' => now(), 'updated_at' => now()];
        if (count($this->pending) >= $this->chunkSize()) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->pending !== []) {
            StudentImportRow::query()->insert($this->pending);
            $this->pending = [];
        }
        $this->import->update(['total_rows' => $this->count]);
    }
}
