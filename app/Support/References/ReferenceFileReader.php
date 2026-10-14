<?php

namespace App\Support\References;

use App\Imports\References\ReferenceSheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;
use ZipArchive;

/**
 * Parses an uploaded mention/program file (XLSX first sheet, or CSV ";"/"," in UTF-8 with or without BOM)
 * into rows keyed by column, matching the French headers of the model whatever their order.
 */
final class ReferenceFileReader
{
    private const MAX_COLUMNS = 20;

    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    /** @return list<array{line: int, values: array<string, string>}> */
    public function read(UploadedFile $file, string $kind): array
    {
        $csv = strtolower($file->getClientOriginalExtension()) === 'csv';
        $path = (string) $file->getRealPath();
        $delimiter = $csv ? $this->delimiter($path) : ',';
        try {
            $this->guard($path, $csv, $delimiter);
            $sheets = Excel::toArray(new ReferenceSheetReader($delimiter), $file, null, $csv ? ExcelType::CSV : ExcelType::XLSX);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable) {
            // Reader messages may contain file paths or cell contents.
            $this->reject('Le fichier est illisible. Utilisez le modèle XLSX ou un CSV UTF-8.');
        }
        $table = array_values($sheets[0] ?? []);
        $columns = $this->mapHeaders($table[0] ?? [], $kind);
        $rows = [];
        foreach (array_slice($table, 1, null, true) as $index => $cells) {
            $values = [];
            foreach ($columns as $key => $position) {
                $values[$key] = $this->cell($cells[$position] ?? null);
            }
            if (implode('', $values) === '') {
                continue;
            }
            if (count($rows) >= ReferenceTransfer::MAX_ROWS) {
                $this->reject('Le fichier dépasse '.ReferenceTransfer::MAX_ROWS.' lignes de données.');
            }
            $rows[] = ['line' => $index + 1, 'values' => $values];
        }
        if ($rows === []) {
            $this->reject('Le fichier ne contient aucune ligne de données.');
        }

        return $rows;
    }

    /** Rejects archives that inflate too much and sheets far beyond the row/column bounds before loading them. */
    private function guard(string $path, bool $csv, string $delimiter): void
    {
        if ($csv && ! mb_check_encoding((string) file_get_contents($path), 'UTF-8')) {
            $this->reject('Le fichier CSV doit être encodé en UTF-8.');
        }
        if (! $csv) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                $this->reject('Le fichier XLSX est illisible.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            $zip->close();
            if ($size > self::MAX_UNCOMPRESSED_BYTES) {
                $this->reject('Le fichier XLSX est trop volumineux une fois décompressé.');
            }
        }
        $reader = $csv ? (new Csv)->setDelimiter($delimiter)->setInputEncoding('UTF-8') : new Xlsx;
        $info = $reader->listWorksheetInfo($path)[0] ?? null;
        if ($info === null) {
            $this->reject('Le fichier ne contient aucune feuille.');
        }
        if ($info['totalRows'] > ReferenceTransfer::MAX_ROWS + 1) {
            $this->reject('Le fichier dépasse '.ReferenceTransfer::MAX_ROWS.' lignes de données.');
        }
        if ($info['totalColumns'] > self::MAX_COLUMNS) {
            $this->reject('Le fichier contient trop de colonnes. Utilisez le modèle.');
        }
    }

    /** ";" when the header line has more semicolons than commas (French Excel), "," otherwise. */
    private function delimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle === false ? '' : (string) fgets($handle, 8192);
        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<string, int> column key => position
     */
    private function mapHeaders(array $headers, string $kind): array
    {
        $expected = [];
        foreach (ReferenceTransfer::columns($kind) as $key => $label) {
            $expected[self::normalizeHeader($label)] = $key;
        }
        $columns = [];
        foreach ($headers as $position => $header) {
            $key = $expected[self::normalizeHeader($this->cell($header))] ?? null;
            if ($key !== null && ! isset($columns[$key])) {
                $columns[$key] = (int) $position;
            }
        }
        $missing = array_diff_key(ReferenceTransfer::columns($kind), $columns);
        if ($missing !== []) {
            $this->reject('Colonnes manquantes : '.implode(', ', $missing).'. Téléchargez le modèle pour obtenir les en-têtes attendus.');
        }

        return $columns;
    }

    private static function normalizeHeader(string $value): string
    {
        return Str::of($value)->replace(['*', '(facultatif)', '(optionnel)'], '')->ascii()->lower()->squish()->toString();
    }

    private function cell(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            $value = (int) $value;
        }
        $value = is_scalar($value) ? (string) $value : '';

        return trim(str_replace("\u{FEFF}", '', $value));
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
