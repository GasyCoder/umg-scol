<?php

namespace App\Actions\Imports;

use App\Imports\StageStudentSheet;
use App\Imports\StageStudentWorkbook;
use App\Models\StudentImport;
use App\Models\User;
use App\Support\StudentImportFileGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class UploadStudentImport
{
    public function handle(User $actor, UploadedFile $file, ?int $establishment, int $year): StudentImport
    {
        Gate::forUser($actor)->authorize('create', StudentImport::class);
        abort_if($actor->hasRole('establishment') && ($establishment === null || $actor->establishment_id !== $establishment), 403);
        try {
            app(StudentImportFileGuard::class)->validate($file);
        } catch (Throwable $error) {
            if ($error instanceof ValidationException) {
                throw $error;
            }
            throw ValidationException::withMessages(['file' => 'Le fichier est illisible ou invalide.']);
        }
        $path = $file->storeAs('files', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'student_imports');
        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'Le stockage du fichier a échoué.']);
        }
        try {
            return DB::transaction(function () use ($actor, $file, $establishment, $year, $path) {
                $import = StudentImport::query()->create(['establishment_id' => $establishment, 'academic_year_id' => $year, 'user_id' => $actor->id, 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'stored_path' => $path, 'disk' => 'student_imports']);
                $sheet = new StageStudentSheet($import);
                Excel::import(new StageStudentWorkbook($sheet), $path, 'student_imports', strtolower($file->getClientOriginalExtension()) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
                $sheet->flush();
                if ($import->total_rows === 0) {
                    throw ValidationException::withMessages(['file' => 'Le fichier ne contient aucune ligne.']);
                }
                $import->refresh();
                $import->audit($actor, 'uploaded');

                return $import;
            });
        } catch (Throwable $error) {
            Storage::disk('student_imports')->delete($path);
            if ($error instanceof ValidationException) {
                throw $error;
            }
            // Reader exception details may include personal values or filesystem paths.
            throw ValidationException::withMessages(['file' => 'Le fichier est illisible. Vérifiez son format et ses en-têtes.']);
        }
    }
}
