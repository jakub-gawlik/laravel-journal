<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A journal record that belongs to a single user.
 */
interface Ownable
{
    public function isOwnedBy(Authenticatable $user): bool;
}
