<?php

namespace App\Providers;

use App\Enums\Security\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            $this->shouldProhibitDestructiveCommands(),
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

    /**
     * Destructive schema commands (migrate:fresh, migrate:refresh, db:wipe, ...)
     * stay blocked in production unless a one-off rebuild has been explicitly
     * authorized via the DB_ALLOW_DESTRUCTIVE_COMMANDS env flag.
     */
    protected function shouldProhibitDestructiveCommands(): bool
    {
        return app()->isProduction()
            && ! config('database.allow_destructive_commands');
    }

    protected function configureGates(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->role === UserRole::Administrador);
    }
}
