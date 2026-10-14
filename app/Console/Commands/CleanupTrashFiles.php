<?php

namespace App\Console\Commands;

use App\Support\Trash\FileDeletionOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTrashFiles extends Command
{
    protected $signature = 'trash:cleanup-files';

    protected $description = 'Reprendre les suppressions privées de fichiers après une purge validée.';

    public function handle(FileDeletionOutbox $outbox): int
    {
        $failed = 0;
        DB::table('pending_file_deletions')->orderBy('id')->chunkById(100, function ($items) use ($outbox, &$failed) {
            foreach ($items as $item) {
                if (! $outbox->clean($item->id)) {
                    $failed++;
                }
            }
        });
        if ($failed > 0) {
            $this->error($failed.' suppression(s) restent en attente.');

            return self::FAILURE;
        }
        $this->info('Aucune suppression de fichier en attente.');

        return self::SUCCESS;
    }
}
