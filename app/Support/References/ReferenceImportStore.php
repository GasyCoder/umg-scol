<?php

namespace App\Support\References;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pending referential imports live in the cache only (no table): normalized rows plus the analysis shown
 * in the preview, under a random token bound to the user and the kind, for 30 minutes and a single confirmation.
 */
final class ReferenceImportStore
{
    public const TTL_MINUTES = 30;

    public const TOKEN_PATTERN = '[A-Za-z0-9]{40}';

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $input
     * @param  array{rows: list<array<string, mixed>>, counts: array<string, int>}  $analysis
     */
    public function put(User $user, string $kind, string $filename, array $input, array $analysis, ?string $token = null): string
    {
        $token ??= Str::random(40);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);
        Cache::put($this->key($token), [
            'user_id' => $user->id, 'kind' => $kind, 'filename' => $filename, 'input' => $input, 'analysis' => $analysis,
            'fingerprint' => ReferenceImportAnalyzer::fingerprint($analysis), 'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $token;
    }

    /**
     * The pending import, or 404 when it expired, was confirmed, or belongs to another user or kind.
     *
     * Keys: user_id, kind, filename, input (normalized rows), analysis {rows, counts}, fingerprint, expires_at.
     *
     * @return array<string, mixed>
     */
    public function get(User $user, string $kind, string $token): array
    {
        $pending = Cache::get($this->key($token));
        abort_unless(is_array($pending) && ($pending['user_id'] ?? null) === $user->id && ($pending['kind'] ?? null) === $kind
            && is_array($pending['input'] ?? null) && is_array($pending['analysis'] ?? null), 404);

        return $pending;
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'reference-import:'.$token;
    }
}
