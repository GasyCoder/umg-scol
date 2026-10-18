<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ReportPolicy;
use App\Support\Branding;
use App\Support\InternalUserProvider;
use App\Support\Queue\QueueWorkers;
use App\Support\StudentPortalAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PowerBoost can stall HTTP/2 module preloads when Chromium reprioritizes them.
        // Native module imports load the same dependencies without those speculative requests.
        Vite::usePreloadTagAttributes(fn (string $src, string $url): array|false => str_ends_with($url, '.js') ? false : []);
        Auth::provider('internal-eloquent', fn ($app, array $config) => new InternalUserProvider($app['hash'], $config['model']));
        Gate::policy(Activity::class, AuditPolicy::class);
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
        Gate::define('dashboard.view-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('dashboard.view'));
        Gate::define('reports.view-ui', [ReportPolicy::class, 'view']);
        Gate::define('reports.export-ui', [ReportPolicy::class, 'export']);
        Gate::define('permissions.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('permissions.manage'));
        Gate::define('branding.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && ! $user->hasRole('establishment') && $user->can('branding.manage'));
        View::composer(['app', 'bourse'], fn ($view) => $view->with('branding', Branding::get()));
        Gate::define('trash.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasAnyRole(['superadmin', 'developer']) && ! $user->hasRole('establishment') && $user->hasUsableEstablishment() && $user->can('trash.manage'));
        Gate::define('trash.purge-ui', fn (User $user) => Gate::forUser($user)->allows('trash.manage-ui') && $user->can('trash.purge'));
        $this->configureDefaults();
        $this->configureDevProcesses();
        $this->configureQueueWorkers();
    }

    /** Workers give a sign of life; a job queued without a live worker of its queue starts one (QueueWorkers). */
    private function configureQueueWorkers(): void
    {
        Event::listen(Looping::class, fn (Looping $event) => QueueWorkers::beat((string) $event->queue));
        // While a job runs (an import lasts up to job_timeout), its worker stays counted as alive.
        Event::listen(JobProcessing::class, fn (JobProcessing $event) => QueueWorkers::beat($event->job->getQueue(), (int) config('student_imports.job_timeout') + 60));
        Event::listen(JobProcessed::class, fn (JobProcessed $event) => QueueWorkers::beat($event->job->getQueue()));
        Event::listen(JobFailed::class, fn (JobFailed $event) => QueueWorkers::beat($event->job->getQueue()));
        Event::listen(WorkerStopping::class, fn () => QueueWorkers::stopped());
        Event::listen(JobQueued::class, fn (JobQueued $event) => QueueWorkers::ensure(is_string($event->queue) ? $event->queue : null));
    }

    /**
     * Background processes of « composer run dev » / « php artisan dev »: one worker per queue
     * (emails first, imports apart) and the scheduler (purges). queue:listen reloads the code for each job.
     * Production runs the same with Supervisor (deploy/supervisor/umg-scol.conf).
     */
    private function configureDevProcesses(): void
    {
        DevCommands::artisan('queue:listen --queue=default --tries=1 --timeout=0', 'queue');
        DevCommands::artisan('queue:listen --queue='.config('student_imports.queue').' --tries=1 --timeout=0', 'imports');
        DevCommands::artisan('schedule:work', 'scheduler');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
