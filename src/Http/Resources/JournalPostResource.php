<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Jgawlik\LaravelJournal\Models\JournalPost;

/**
 * @mixin JournalPost
 */
class JournalPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'cover_image' => $this->cover_image,
            'category_id' => $this->category_id,
            'category' => new JournalCategoryResource($this->whenLoaded('category')),
            'tags' => JournalTagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
