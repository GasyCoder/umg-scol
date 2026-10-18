<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Scholarships\DdbNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveDdbNumbering
{
    /** Numbers already given keep their value; the new pattern applies to the next receptions. */
    public function handle(User $actor, string $pattern, int $digits): void
    {
        Gate::forUser($actor)->authorize('branding.manage-ui');
        DB::transaction(function () use ($actor, $pattern, $digits) {
            $before = DdbNumber::settings();
            DdbNumber::put(['pattern' => $pattern, 'digits' => $digits]);
            if ($before !== ['pattern' => $pattern, 'digits' => $digits]) {
                activity('platform')->causedBy($actor)->event('ddb_numbering_updated')
                    ->withProperties(['old' => $before, 'new' => ['pattern' => $pattern, 'digits' => $digits]])->log('ddb_numbering_updated');
            }
        });
    }
}
