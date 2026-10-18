<?php

namespace App\Jobs;

use App\Actions\Imports\CommitStudentImport;
use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Confirmation of a large import file, started by CommitStudentImport::run(). */
class CommitStudentImportJob implements ShouldQueue
{
    use Queueable;

    /** The confirmation is all or nothing: never replayed automatically. */
    public int $tries = 1;

    public int $timeout;

    public function __construct(public User $actor, public StudentImport $import)
    {
        $this->timeout = (int) config('student_imports.job_timeout');
        $this->onQueue((string) config('student_imports.queue'));
    }

    public function handle(CommitStudentImport $action): void
    {
        $action->handle($this->actor, $this->import, true);
    }

    /** Interrupted before its failure marker (timeout, lost worker): nothing was kept, the import stays ready. */
    public function failed(?Throwable $error): void
    {
        StudentImport::query()->whereKey($this->import->id)->where('status', StudentImportStatus::Committing)->update([
            'status' => StudentImportStatus::Ready,
            // A refusal of the action (inactive establishment, changed status) is shown as is.
            'failure_reason' => $error instanceof ValidationException ? mb_substr((string) collect($error->errors())->flatten()->first(), 0, 255) : 'La confirmation n’a pas abouti et a été annulée intégralement. Relancez-la ; si l’échec se répète, contactez l’administrateur.',
        ]);
    }
}
