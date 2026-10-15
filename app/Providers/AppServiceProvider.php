<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\User;
use App\Policies\AuditPolicy;
use App\Policies\ReportPolicy;
use App\Support\Branding;
use App\Support\InternalUserProvider;
use App\Support\StudentPortalAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        Auth::provider('internal-eloquent', fn ($app, array $config) => new InternalUserProvider($app['hash'], $config['model']));
        Gate::policy(Activity::class, AuditPolicy::class);
        Gate::define('reports.view', [ReportPolicy::class, 'view']);
        Gate::define('reports.export', [ReportPolicy::class, 'export']);
        Gate::define('permissions.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && $user->can('permissions.manage'));
        Gate::define('branding.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasUsableEstablishment() && ! $user->hasRole('establishment') && $user->can('branding.manage'));
        View::composer(['app', 'bourse'], fn ($view) => $view->with('branding', Branding::get()));
        Gate::define('trash.manage-ui', fn (User $user) => StudentPortalAccess::internal($user) && $user->hasAnyRole(['superadmin', 'developer']) && ! $user->hasRole('establishment') && $user->hasUsableEstablishment() && $user->can('trash.manage'));
        Gate::define('trash.purge-ui', fn (User $user) => Gate::forUser($user)->allows('trash.manage-ui') && $user->can('trash.purge'));
        $this->configureDefaults();
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
