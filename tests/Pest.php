<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jgawlik\LaravelJournal\Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
