<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

use Jgawlik\LaravelJournal\Rules\Slug;

class JournalPostIndexRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return ['title', 'created_at', 'updated_at'];
    }

    protected function searchable(): array
    {
        return ['title', 'content'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'category' => ['sometimes', new Slug, 'max:255'],
            'tag' => ['sometimes', new Slug, 'max:255'],
        ];
    }
}
