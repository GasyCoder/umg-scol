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

    /** The summary block of the template has started: nothing below is a student. */
    private bool $summary = false;

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
        // Rows of the model holding only the BOURSIER/TAUX formulas are empty lines, not students.
        $filled = array_filter($raw, fn ($v) => $v !== null && $v !== '' && ! (is_string($v) && str_starts_with($v, '=')));
        if ($this->summary || $filled === [] || StudentImportColumns::isLegendRow($raw)) {
            return;
        }
        if (StudentImportColumns::isSummaryStart($raw)) {
            $this->summary = true;

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
