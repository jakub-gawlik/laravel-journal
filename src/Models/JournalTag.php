<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jgawlik\LaravelJournal\Contracts\Ownable;
use Jgawlik\LaravelJournal\Database\Factories\JournalTagFactory;
use Jgawlik\LaravelJournal\Models\Concerns\BelongsToOwner;

class JournalTag extends Model implements Ownable
{
    use BelongsToOwner;

    /** @use HasFactory<JournalTagFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
    ];

    /**
     * @return BelongsToMany<JournalPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(JournalPost::class, 'journal_post_tag')->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Slugs are only unique per user, so resolve {tag} among the
     * authenticated user's own tags.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->resolveRouteBindingQuery($this->newQuery(), $value, $field)
            ->where('user_id', auth()->id())
            ->first();
    }

    protected static function newFactory(): JournalTagFactory
    {
        return JournalTagFactory::new();
    }
}
