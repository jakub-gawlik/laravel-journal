<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Tests;

use Jgawlik\LaravelJournal\JournalServiceProvider;
use Jgawlik\LaravelJournal\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [JournalServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('journal.user_model', User::class);
        // Sanctum isn't a dependency of the package; the session guard is enough for tests.
        $app['config']->set('journal.routes.middleware', ['api', 'auth']);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Register (rather than run) Laravel's users migration so RefreshDatabase's migrate:fresh includes it.
        $this->app->make('migrator')->path(default_migration_path());
    }
}
