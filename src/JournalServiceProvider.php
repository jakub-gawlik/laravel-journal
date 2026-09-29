<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Models\JournalTag;
use Jgawlik\LaravelJournal\Policies\JournalPostPolicy;
use Jgawlik\LaravelJournal\Policies\JournalTagPolicy;

class JournalServiceProvider extends ServiceProvider
{
    /**
     * Gate that controls who may create, update and delete the shared
     * journal categories. Host applications override it in their own
     * AuthServiceProvider / AppServiceProvider.
     */
    public const MANAGE_CATEGORIES = 'manage-journal-categories';

    /**
     * The host application's user model that owns posts and tags.
     *
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        return config('journal.user_model');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/journal.php', 'journal');
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerPolicies();

        if (config('journal.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/journal.php' => config_path('journal.php'),
            ], 'journal-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'journal-migrations');
        }
    }

    protected function registerRoutes(): void
    {
        if (! config('journal.routes.enabled', true) || $this->app->routesAreCached()) {
            return;
        }

        Route::prefix(config('journal.routes.prefix', 'api/journal'))
            ->middleware(config('journal.routes.middleware', ['api', 'auth:sanctum']))
            ->name(config('journal.routes.name', 'journal.'))
            ->group(__DIR__.'/../routes/api.php');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(JournalPost::class, JournalPostPolicy::class);
        Gate::policy(JournalTag::class, JournalTagPolicy::class);

        // Deny by default; host apps opt in with Gate::define(...) after this provider boots.
        if (! Gate::has(self::MANAGE_CATEGORIES)) {
            Gate::define(self::MANAGE_CATEGORIES, fn ($user) => false);
        }
    }
}
