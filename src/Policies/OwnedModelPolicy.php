<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Jgawlik\LaravelJournal\Contracts\Ownable;

/**
 * Any user may list and create their own records; only the owner may view, update or delete one.
 */
abstract class OwnedModelPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Ownable $model): Response
    {
        return $this->owner($user, $model);
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Ownable $model): Response
    {
        return $this->owner($user, $model);
    }

    public function delete(Authenticatable $user, Ownable $model): Response
    {
        return $this->owner($user, $model);
    }

    /**
     * Journals are private: respond 404 so other users can't probe which records exist.
     */
    protected function owner(Authenticatable $user, Ownable $model): Response
    {
        return $model->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
