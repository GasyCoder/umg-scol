<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentCorrectionRequest;
use App\Models\User;
use App\Notifications\StudentActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;

/** Ownership and current permissions/perimeter apply to the list, badge and every mutation. */
final class NotificationInbox
{
    /** @return Builder<DatabaseNotification> */
    public static function query(User $user): Builder
    {
        $query = $user->notifications()->getQuery()->where('type', StudentActivity::class);
        if (! Gate::forUser($user)->allows('viewAny', DatabaseNotification::class) || $user->hasRole('developer')) {
            return $query->whereRaw('1 = 0');
        }

        $corrections = Gate::forUser($user)->allows('viewAny', StudentCorrectionRequest::class);
        $confirmations = $user->can('students.view');
        if (! $corrections && ! $confirmations) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visible) use ($user, $corrections, $confirmations) {
            if ($corrections) {
                $visible->orWhere(fn (Builder $kind) => $kind->where('data->kind', StudentActivity::CORRECTION_REQUESTED)
                    ->whereIn('data->subject_uuid', StudentCorrectionRequest::query()->visibleTo($user)->select('uuid')));
            }
            if ($confirmations) {
                $students = Student::query()->visibleTo($user)
                    ->when($user->hasRole('establishment'), fn ($student) => $student->currentlyDeclaredBy($user->establishment_id));
                $visible->orWhere(fn (Builder $kind) => $kind->where('data->kind', StudentActivity::INFORMATION_CONFIRMED)
                    ->whereIn('data->student_uuid', $students->select('uuid')));
            }
        });
    }

    /** @return array{id: string, data: array{kind: string, title: string, message: string, href: string}, read_at: string|null, created_at: string|null} */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'data' => [
                'kind' => $data['kind'], 'title' => $data['title'], 'message' => $data['message'],
                // Never redirect to a URL supplied by a client or copied from stored notification data.
                'href' => $data['kind'] === StudentActivity::CORRECTION_REQUESTED ? '/corrections/'.$data['subject_uuid'] : '/students/'.$data['student_uuid'],
            ],
            'read_at' => $notification->read_at?->toISOString(), 'created_at' => $notification->created_at?->toISOString(),
        ];
    }

    /** @return array{unread: int, recent: list<array<string, mixed>>} */
    public static function feed(?User $user): array
    {
        if ($user === null || ! $user->notifications()->where('type', StudentActivity::class)->exists()) {
            return ['unread' => 0, 'recent' => []];
        }
        $query = self::query($user);

        return [
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'recent' => array_values($query->orderByDesc('id')->limit(8)->get()->map(fn (DatabaseNotification $item) => self::present($item))->all()),
        ];
    }
}
