<?php

use Illuminate\Database\Migrations\Migrator;
use Jgawlik\LaravelJournal\JournalServiceProvider;

function freshMigratorPathsAfterBoot(bool $runMigrations): array
{
    $migrator = new Migrator(app('migration.repository'), app('db'), app('files'));
    app()->instance('migrator', $migrator);

    config(['journal.run_migrations' => $runMigrations]);
    (new JournalServiceProvider(app()))->boot();

    return array_map('realpath', $migrator->paths());
}

it('runs the package migrations by default', function () {
    expect(freshMigratorPathsAfterBoot(true))->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('skips the package migrations when they are published', function () {
    expect(freshMigratorPathsAfterBoot(false))->toBeEmpty();
});

it('registers no routes when journal.routes.enabled is false', function () {
    $before = count(app('router')->getRoutes());

    config(['journal.routes.enabled' => false]);
    (new JournalServiceProvider(app()))->boot();

    expect(count(app('router')->getRoutes()))->toBe($before);
});
