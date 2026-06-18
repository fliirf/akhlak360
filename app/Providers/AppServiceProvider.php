<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Paginator::useBootstrapFour();

        Gate::define('view-master-data-menu', fn (User $user): bool => $user->hasRole(['admin_hr', 'it_admin']));
        Gate::define('view-master-data', fn (User $user): bool => $user->hasRole(['admin_hr']));
        Gate::define('view-hris-sync', fn (User $user): bool => $user->hasRole(['admin_hr', 'it_admin']));
        Gate::define('manage-hris-sync', fn (User $user): bool => $user->hasRole('admin_hr'));
        Gate::define('view-assessment-cycle', fn (User $user): bool => $user->hasRole(['admin_hr', 'supervisor']));
        Gate::define('manage-assessment-cycle', fn (User $user): bool => $user->hasRole(['admin_hr']));
        Gate::define('view-peer-approval', fn (User $user): bool => $user->hasRole(['admin_hr', 'supervisor']));
        Gate::define('approve-peers', fn (User $user): bool => $user->hasRole(['supervisor']));
        Gate::define('assign-assessors', fn (User $user): bool => $user->hasRole(['admin_hr']));
        Gate::define('view-assessment', fn (User $user): bool => $user->hasRole(['supervisor', 'employee']));
        Gate::define('view-analytics', fn (User $user): bool => $user->hasRole(['admin_hr', 'management']));
        Gate::define('view-core-value-dashboard', fn (User $user): bool => $user->hasRole(['admin_hr', 'management']));
        Gate::define('view-idp', fn (User $user): bool => $user->hasRole(['admin_hr', 'supervisor', 'employee', 'management']));
        Gate::define('view-team-idp', fn (User $user): bool => $user->hasRole('supervisor'));
        Gate::define('view-personal-idp', fn (User $user): bool => $user->hasRole('employee'));
        Gate::define('view-team-results', fn (User $user): bool => $user->hasRole('supervisor'));
        Gate::define('view-personal-results', fn (User $user): bool => $user->hasRole('employee'));
        Gate::define('view-report-export', fn (User $user): bool => $user->hasRole(['admin_hr', 'management']));
        Gate::define('view-report-history', fn (User $user): bool => $user->hasRole(['admin_hr', 'management', 'it_admin']));
        Gate::define('view-notifications', fn (User $user): bool => $user->hasRole(['admin_hr', 'supervisor', 'employee', 'management', 'it_admin']));
        Gate::define('view-audit-compliance', fn (User $user): bool => $user->hasRole(['admin_hr', 'it_admin']));
        Gate::define('view-system-settings', fn (User $user): bool => $user->hasRole(['it_admin']));
    }
}
