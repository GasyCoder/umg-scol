<?php

namespace App\Actions\Students;

use App\Enums\StudentRecordStatus;
use App\Models\StudentRecord;
use App\Models\User;
use App\Support\Trash\TrashManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Group action on the situations ticked in the student list. Each row goes through the same
 * Action as the single button (authorisation, checks, audit), in its own transaction: a refused
 * row never blocks the others, and the result tells what was done.
 */
class BulkStudentRecords
{
    /**
     * @param  list<string>  $uuids
     * @return array{done:int,unchanged:int,refused:int}
     */
    public function handle(User $actor, string $action, array $uuids): array
    {
        $result = ['done' => 0, 'unchanged' => 0, 'refused' => 0];
        $records = StudentRecord::query()->visibleTo($actor)->whereIn('uuid', $uuids)->with('student')->orderBy('id')->get();
        $result['refused'] += count($uuids) - $records->count();
        if ($action === 'trash') {
            // The row's bin button moves the student (with its situations): same here, once per student.
            foreach ($records->pluck('student')->filter()->unique('id') as $student) {
                $this->attempt($result, fn () => app(TrashManager::class)->handle($actor, 'students', $student->uuid, 'trash'));
            }

            return $result;
        }
        $target = $action === 'activate' ? StudentRecordStatus::Active : StudentRecordStatus::Inactive;
        foreach ($records as $record) {
            if ($record->status === $target) {
                $result['unchanged']++;

                continue;
            }
            $this->attempt($result, fn () => app(SaveStudentRecord::class)->handle($actor, $record->student, $record, ['status' => $target->value]));
        }

        return $result;
    }

    /** @param array{done:int,unchanged:int,refused:int} $result */
    private function attempt(array &$result, callable $operation): void
    {
        try {
            $operation();
            $result['done']++;
        } catch (ValidationException|AuthorizationException|HttpException) {
            $result['refused']++;
        }
    }
}
