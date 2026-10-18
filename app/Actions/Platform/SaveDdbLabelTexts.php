<?php

namespace App\Actions\Platform;

use App\Models\User;
use App\Support\Scholarships\DdbLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveDdbLabelTexts
{
    /** @param array<string,string|null> $values Empty values restore the default text. */
    public function handle(User $actor, array $values): void
    {
        Gate::forUser($actor)->authorize('branding.manage-ui');
        $values = collect(DdbLabel::keys())->mapWithKeys(fn (string $key) => [$key => filled($values[$key] ?? null) ? trim((string) $values[$key]) : null])->all();
        DB::transaction(function () use ($actor, $values) {
            $before = DdbLabel::raw();
            DdbLabel::put($values);
            $changed = array_keys(array_filter($values, fn (?string $value, string $key) => (string) $value !== $before[$key], ARRAY_FILTER_USE_BOTH));
            if ($changed !== []) {
                // Texts printed on blank labels: no personal data, logged with their values.
                activity('platform')->causedBy($actor)->event('ddb_labels_updated')
                    ->withProperties(['fields' => $changed, 'old' => array_intersect_key($before, array_flip($changed)), 'new' => array_intersect_key($values, array_flip($changed))])->log('ddb_labels_updated');
            }
        });
    }
}
