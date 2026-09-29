<?php

use Illuminate\Support\Facades\Route;
use Jgawlik\LaravelJournal\Http\Controllers\JournalCategoryController;
use Jgawlik\LaravelJournal\Http\Controllers\JournalPostController;
use Jgawlik\LaravelJournal\Http\Controllers\JournalTagController;

// Prefix, middleware and name prefix are applied by JournalServiceProvider (see config/journal.php).

Route::apiResource('posts', JournalPostController::class)
    ->parameters(['posts' => 'post']);

Route::apiResource('categories', JournalCategoryController::class)
    ->parameters(['categories' => 'category']);

Route::apiResource('tags', JournalTagController::class)
    ->parameters(['tags' => 'tag']);
