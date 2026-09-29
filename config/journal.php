<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The prefix, middleware and route-name prefix applied to every journal
    | endpoint. Set "enabled" to false to register your own routes instead.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'api/journal',
        'middleware' => ['api', 'auth:sanctum'],
        'name' => 'journal.',
    ],

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | The model that owns journal posts and tags. The migration uses it to
    | create a foreign key of the matching type (bigint, UUID or ULID).
    |
    */

    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | Run the package migrations straight from the vendor directory. If you
    | publish them with --tag=journal-migrations, set this to false, or
    | they will run twice and fail because the tables already exist.
    |
    */

    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'per_page' => 15,
        'max_per_page' => 100,
    ],

];
