<?php

namespace App\Actions\Scholarships;

use App\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The same decision on several files: each one goes through TransitionScholarshipApplication
 * (its own transaction, rights, checks, verified situation, history and audit). A refused file
 * never blocks the others.
 */
class BulkTransitionScholarshipApplications
{
    public function __construct(private TransitionScholarshipApplication $transition) {}

    /**
     * @param  list<string>  $uuids
     * @return array{done:int,unchanged:int,refused:int,done_uuids:list<string>}
     */
    public function handle(User $actor, ScholarshipApplicationStatus $target, array $uuids, ?string $reason): array
    {
        $result = ['done' => 0, 'unchanged' => 0, 'refused' => 0, 'done_uuids' => []];
        $applications = ScholarshipApplication::query()->whereIn('uuid', $uuids)->get()->keyBy('uuid');
        foreach ($uuids as $uuid) {
            $application = $applications->get($uuid);
            if ($application === null) {
                $result['refused']++;
            } elseif ($application->status === $target) {
                $result['unchanged']++;
            } else {
                try {
                    // From the list: a validation records the checks still to do as conforming.
                    $this->transition->handle($actor, $application, $target, $reason, true);
                    $result['done']++;
                    $result['done_uuids'][] = $uuid;
                } catch (AuthorizationException|ValidationException) {
                    $result['refused']++;
                }
            }
        }

        return $result;
    }
}
