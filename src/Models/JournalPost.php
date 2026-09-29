<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Jgawlik\LaravelJournal\Contracts\Ownable;
use Jgawlik\LaravelJournal\Database\Factories\JournalPostFactory;
use Jgawlik\LaravelJournal\Models\Concerns\BelongsToOwner;

class JournalPost extends Model implements Ownable
{
    use BelongsToOwner;

    /** @use HasFactory<JournalPostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'cover_image',
        'category_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalPost $post) {
            if (blank($post->slug)) {
                $post->slug = static::uniqueSlugFor($post->user_id, (string) $post->title);
            }
        });
    }

    /**
     * Build a slug from the given text that is unique among the user's posts.
     */
    public static function uniqueSlugFor(mixed $userId, string $text, mixed $ignoreId = null): string
    {
        // Trim after cutting, so the cut can't leave a trailing hyphen (and a "--2" suffix later).
        $base = rtrim(Str::limit(Str::slug($text), 240, ''), '-') ?: 'post';
        $slug = $base;

        for ($i = 2; static::slugTaken($userId, $slug, $ignoreId); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    protected static function slugTaken(mixed $userId, string $slug, mixed $ignoreId): bool
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * @return BelongsTo<JournalCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JournalCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<JournalTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(JournalTag::class, 'journal_post_tag')->withTimestamps();
    }

    protected static function newFactory(): JournalPostFactory
    {
        return JournalPostFactory::new();
    }
}
