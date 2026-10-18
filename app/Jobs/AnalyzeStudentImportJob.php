<?php

namespace App\Jobs;

use App\Actions\Imports\AnalyzeStudentImport;
use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Analysis of a large import file, started by AnalyzeStudentImport::run(). */
class AnalyzeStudentImportJob implements ShouldQueue
{
    use Queueable;

    /** A second attempt would analyze again from the start: the agent restarts it instead. */
    public int $tries = 1;

    public int $timeout;

    /** @param array<string,int> $mapping */
    public function __construct(public User $actor, public StudentImport $import, public array $mapping)
    {
        $this->timeout = (int) config('student_imports.job_timeout');
        $this->onQueue((string) config('student_imports.queue'));
    }

    public function handle(AnalyzeStudentImport $action): void
    {
        $action->handle($this->actor, $this->import, $this->mapping, true);
    }

    /** The analysis rolled back: the import goes back to its previous step, with a message for the agent. */
    public function failed(?Throwable $error): void
    {
        StudentImport::query()->whereKey($this->import->id)->where('status', StudentImportStatus::Analyzing)->update([
            'status' => $this->import->getAttribute('analyzed_at') === null ? StudentImportStatus::Uploaded : StudentImportStatus::Ready,
            // A refusal of the action (inactive establishment, changed status) is shown as is.
            'failure_reason' => $error instanceof ValidationException ? mb_substr((string) collect($error->errors())->flatten()->first(), 0, 255) : 'L’analyse n’a pas abouti. Relancez-la ; si l’échec se répète, contactez l’administrateur.',
        ]);
    }
}
