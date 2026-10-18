<?php

namespace App\Support\Queue;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Workers started without anyone: when a job is queued and no worker of its queue gave a sign of life,
 * a worker is launched in the background; it empties the queue, then stops. Works the same in development
 * (php artisan serve) and on a shared host without Supervisor (o2switch), where the scheduler (cron)
 * is the safety net. The heartbeat is kept outside the database, like the import progress.
 */
class QueueWorkers
{
    /** Seconds without sign of life before a worker counts as stopped (it beats at every loop). */
    private const IDLE = 20;

    /** Queues of the worker running in this process, forgotten when it stops. */
    /** @var array<string,true> */
    private static array $mine = [];

    private static function store(): Repository
    {
        return Cache::store((string) config('student_imports.progress_store'));
    }

    /** Sign of life of a worker of these queues ("default", "imports" or "default,imports"). */
    public static function beat(string $queues, int $seconds = self::IDLE): void
    {
        foreach (array_filter(array_map('trim', explode(',', $queues))) as $queue) {
            self::store()->put('queue-workers:'.$queue, now()->getTimestamp(), $seconds);
            self::$mine[$queue] = true;
        }
    }

    /** The worker of this process stops (queue empty, restart, limit): its queues have no live worker any more. */
    public static function stopped(): void
    {
        foreach (array_keys(self::$mine) as $queue) {
            self::store()->forget('queue-workers:'.$queue);
        }
        self::$mine = [];
    }

    public static function alive(string $queue): bool
    {
        return self::store()->has('queue-workers:'.$queue);
    }

    /**
     * Launches a worker for the queue when none is alive; never two launches within the same seconds.
     * $force: a job has been waiting too long, the sign of life is not trusted (worker killed during a job).
     */
    public static function ensure(?string $queue, bool $force = false): void
    {
        $queue = $queue ?: 'default';
        if (! config('queue.auto_worker.enabled') || (! $force && self::alive($queue)) || ! self::store()->add('queue-workers:launch:'.$queue, 1, 10)) {
            return;
        }
        try {
            Process::path(base_path())->run(self::command($queue));
        } catch (Throwable $error) {
            // Process functions disabled by the host: the scheduler (cron) starts the worker within a minute.
            Log::warning('Lancement automatique du worker impossible : '.$error->getMessage());
        }
    }

    /** Detached worker that stops once its queue is empty (no long-running process on a shared host). */
    public static function command(string $queue): string
    {
        $imports = $queue === (string) config('student_imports.queue');
        $options = $imports
            ? '--tries=1 --timeout='.(int) config('student_imports.job_timeout').' --memory=1024'
            : '--tries=3 --backoff=10 --timeout=60';
        $log = storage_path('logs/worker-'.preg_replace('/[^a-z0-9_-]/i', '', $queue).'.log');

        // An import holds the whole file analysis in memory: the host's CLI limit may be lower.
        $php = escapeshellarg(self::php()).($imports ? ' -d memory_limit=1024M' : '');
        $worker = 'exec nohup '.$php.' artisan queue:work --queue='.escapeshellarg($queue).' --stop-when-empty --sleep=1 '.$options
            .' < /dev/null >> '.escapeshellarg($log).' 2>&1';

        // Descriptors inherited from the web request (its connection) are closed first: otherwise the
        // response would only end with the worker.
        return 'sh -c '.escapeshellarg('for fd in /proc/$$/fd/*; do n=${fd##*/}; [ "$n" -gt 2 ] 2>/dev/null && eval "exec $n>&-"; done; '.$worker).' > /dev/null 2>&1 &';
    }

    /** PHP command line binary: the web server's PHP_BINARY is php-fpm on a hosting, not the CLI. */
    public static function php(): string
    {
        $configured = (string) config('queue.auto_worker.php');
        if ($configured !== '') {
            return $configured;
        }

        return in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? PHP_BINARY : 'php';
    }
}
