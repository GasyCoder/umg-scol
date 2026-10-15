<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * An event of the student portal, shown in the bell of the staff concerned.
 * Carries the student's name, the kind of event and the link to open: never the values.
 */
class StudentActivity extends Notification
{
    public const CORRECTION_REQUESTED = 'correction_requested';

    public const INFORMATION_CONFIRMED = 'information_confirmed';

    /** Label of each type, also used by the preferences. */
    public const TYPES = [
        self::CORRECTION_REQUESTED => 'Demande de correction d’un étudiant',
        self::INFORMATION_CONFIRMED => 'Informations confirmées par un étudiant',
    ];

    public function __construct(
        public readonly string $kind,
        public readonly string $message,
        public readonly string $studentUuid,
        public readonly string $subjectUuid,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->wantsNotification($this->kind) ? ['database'] : [];
    }

    /** @return array<string,string> */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => $this->kind, 'title' => self::TYPES[$this->kind], 'message' => $this->message,
            'student_uuid' => $this->studentUuid, 'subject_uuid' => $this->subjectUuid,
        ];
    }
}
