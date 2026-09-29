<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

class JournalTagIndexRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return ['name', 'slug', 'created_at', 'updated_at'];
    }

    protected function searchable(): array
    {
        return ['name', 'slug'];
    }
}
