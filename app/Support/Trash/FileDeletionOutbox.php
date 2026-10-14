<?php

namespace App\Support\Trash;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FileDeletionOutbox
{
    /** Schedule inside the purge transaction; retry information survives a storage failure. */
    public function schedule(string $disk, string $path): void
    {
        $id = DB::table('pending_file_deletions')->insertGetId(['disk' => $disk, 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
        DB::afterCommit(fn () => $this->clean($id));
    }

    public function clean(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $item = DB::table('pending_file_deletions')->where('id', $id)->lockForUpdate()->first();
            if ($item === null) {
                return true;
            }
            try {
                $disk = Storage::disk($item->disk);
                if ($disk->exists($item->path) && ! $disk->delete($item->path)) {
                    throw new RuntimeException('File deletion failed.');
                }
                DB::table('pending_file_deletions')->where('id', $id)->delete();

                return true;
            } catch (Throwable $error) {
                DB::table('pending_file_deletions')->where('id', $id)->update(['attempts' => $item->attempts + 1, 'last_error' => $error::class, 'attempted_at' => now(), 'updated_at' => now()]);
                Log::warning('Suppression de fichier en attente de reprise.', ['cleanup_id' => $id, 'error_type' => $error::class]);

                return false;
            }
        });
    }
}
