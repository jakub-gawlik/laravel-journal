<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Jgawlik\LaravelJournal\Models\JournalCategory;

/**
 * @mixin JournalCategory
 */
class JournalCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'posts_count' => $this->whenCounted('posts'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
