<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jgawlik\LaravelJournal\Database\Factories\JournalCategoryFactory;

/**
 * @property string $name
 * @property string $slug
 */
class JournalCategory extends Model
{
    /** @use HasFactory<JournalCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany<JournalPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(JournalPost::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): JournalCategoryFactory
    {
        return JournalCategoryFactory::new();
    }
}
