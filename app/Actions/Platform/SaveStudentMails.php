<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\StudentMails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveStudentMails
{
    /** @param array<string,bool> $values */
    public function handle(User $actor, array $values): void
    {
        Gate::forUser($actor)->authorize('branding.manage-ui');
        $values = collect(StudentMails::KINDS)->mapWithKeys(fn ($label, string $key) => [$key => (bool) ($values[$key] ?? false)])->all();
        DB::transaction(function () use ($actor, $values) {
            $before = StudentMails::settings();
            StudentMails::put($values);
            if ($before !== $values) {
                activity('platform')->causedBy($actor)->event('student_mails_updated')
                    ->withProperties(['old' => $before, 'new' => $values])->log('student_mails_updated');
            }
        });
    }
}
