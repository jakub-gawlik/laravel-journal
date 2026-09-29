<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
