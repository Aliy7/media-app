<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * HorizonServiceProvider
 *
 * Registers Laravel Horizon and controls access to the /horizon dashboard.
 *
 * Access policy (Decision D-022):
 *   Only the authenticated user whose email matches HORIZON_ADMIN_EMAIL may
 *   access /horizon in non-local environments. This avoids adding an admin
 *   role to the schema, which is out of scope for this assignment.
 *
 * In the `local` environment all authenticated users are permitted so
 * development is never blocked by email configuration.
 *
 * To restrict access in production, set in .env:
 *   HORIZON_ADMIN_EMAIL=your-email@example.com
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * The gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            // Any authenticated user may access Horizon in local development.
            if (app()->environment('local')) {
                return true;
            }

            // All other environments: restrict to the configured admin email.
            return $user->email === env('HORIZON_ADMIN_EMAIL');
        });
    }
}
