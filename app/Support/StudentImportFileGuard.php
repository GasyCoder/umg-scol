<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class StudentImportFileGuard
{
    public function validate(UploadedFile $file): void
    {
        $xlsx = strtolower($file->getClientOriginalExtension()) === 'xlsx';
        if ($xlsx) {
            $zip = new ZipArchive;
            if ($zip->open($file->getPathname()) !== true) {
                $this->reject();
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                if ($entry !== false) {
                    $size += $entry['size'];
                }
            }
            $zip->close();
            if ($size > 100 * 1024 * 1024) {
                $this->reject();
            }
        }
        $reader = IOFactory::createReader($xlsx ? 'Xlsx' : 'Csv');
        $sheets = $reader->listWorksheetInfo($file->getPathname());
        if (count($sheets) !== 1 || $sheets[0]['totalRows'] > (int) config('student_imports.max_rows') + 1 || $sheets[0]['totalColumns'] > (int) config('student_imports.max_columns')) {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['file' => 'Le fichier doit contenir une seule feuille et respecter les limites de lignes, colonnes et taille décompressée.']);
    }
}
