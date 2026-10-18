<?php

namespace App\Console\Commands;

use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeStudentImports extends Command
{
    protected $signature = 'students:purge-imports';

    protected $description = 'Purger les fichiers et données personnelles de staging après leur durée de conservation.';

    public function handle(): int
    {
        $count = 0;
        StudentImport::withTrashed()->whereNull('purged_at')->where('created_at', '<', now()->subDays((int) config('student_imports.retention_days')))->whereNotIn('status', ['analyzing', 'committing'])->chunkById(100, function ($imports) use (&$count) {
            foreach ($imports as $item) {
                DB::transaction(function () use ($item, &$count) {
                    $import = StudentImport::withTrashed()->lockForUpdate()->findOrFail($item->id);
                    if ($import->purged_at !== null || in_array($import->status, [StudentImportStatus::Analyzing, StudentImportStatus::Committing], true)) {
                        return;
                    }
                    Storage::disk($import->disk)->delete($import->stored_path);
                    $import->rows()->update(['raw_data' => '[]', 'corrections' => null, 'identity_updates' => null, 'normalized_data' => null, 'errors' => null, 'warnings' => null]);
                    $import->update(['purged_at' => now(), 'headers' => [], 'mapping' => [], 'original_filename' => '[fichier purgé]', 'status' => in_array($import->status, [StudentImportStatus::Uploaded, StudentImportStatus::Ready], true) ? StudentImportStatus::Cancelled : $import->status]);
                    $count++;
                });
            }
        });
        $this->info($count.' import(s) purgé(s).');

        return self::SUCCESS;
    }
}
