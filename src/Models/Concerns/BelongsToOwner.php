<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Models\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jgawlik\LaravelJournal\JournalServiceProvider;

/**
 * Ownership helpers for models with a user_id column.
 *
 * @property int|string $user_id Matches the key type of the configured user model.
 */
trait BelongsToOwner
{
    /**
     * Compares as strings, so it works for int, UUID and ULID keys.
     */
    public function isOwnedBy(Authenticatable $user): bool
    {
        return (string) $this->user_id === (string) $user->getAuthIdentifier();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, Authenticatable $user): void
    {
        $query->where($this->qualifyColumn('user_id'), $user->getAuthIdentifier());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(JournalServiceProvider::userModel(), 'user_id');
    }
}
