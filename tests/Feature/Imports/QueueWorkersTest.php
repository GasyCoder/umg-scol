<?php

use App\Support\Queue\QueueWorkers;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['queue.auto_worker.enabled' => true, 'queue.auto_worker.php' => '/opt/php/bin/php', 'student_imports.progress_store' => 'array']);
    Process::fake();
});

it('launches a detached worker that stops once its queue is empty, once, and only without a live worker', function () {
    QueueWorkers::ensure('imports');
    QueueWorkers::ensure('imports');
    Process::assertRanTimes(fn ($process) => str_contains($process->command, 'queue:work --queue=') && str_contains($process->command, 'imports') && str_contains($process->command, '--stop-when-empty'), 1);

    QueueWorkers::beat('default,imports');
    expect(QueueWorkers::alive('default'))->toBeTrue();
    QueueWorkers::ensure('default');
    Process::assertRanTimes(fn ($process) => str_contains($process->command, 'default'), 0);
});

it('builds a worker command detached from the web request, with the host CLI binary and the import limits', function () {
    $command = QueueWorkers::command('imports');
    expect($command)->toStartWith('sh -c ')->toEndWith(' > /dev/null 2>&1 &')
        ->toContain('/opt/php/bin/php')->toContain('-d memory_limit=1024M')->toContain('--tries=1 --timeout=600')->toContain('< /dev/null')
        ->and(QueueWorkers::command('default'))->toContain('--tries=3')->not->toContain('memory_limit');
});

it('never launches a worker when disabled', function () {
    config(['queue.auto_worker.enabled' => false]);
    QueueWorkers::ensure('imports');
    Process::assertNothingRan();
});

it('forgets the sign of life of a worker that stops, so that the next job starts another one at once', function () {
    QueueWorkers::beat('imports');
    expect(QueueWorkers::alive('imports'))->toBeTrue();
    event(new WorkerStopping(0));
    expect(QueueWorkers::alive('imports'))->toBeFalse();
    QueueWorkers::ensure('imports');
    Process::assertRanTimes(fn ($process) => str_contains($process->command, 'imports'), 1);
});

it('starts a worker anyway when a job waits too long behind a stale sign of life', function () {
    QueueWorkers::beat('imports', 600);
    QueueWorkers::ensure('imports');
    Process::assertNothingRan();
    QueueWorkers::ensure('imports', true);
    Process::assertRanTimes(fn ($process) => str_contains($process->command, 'imports'), 1);
});
