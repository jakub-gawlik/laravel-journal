<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Shared query-string validation for paginated, searchable, sortable index endpoints.
 */
abstract class IndexRequest extends FormRequest
{
    /**
     * Escape character for LIKE patterns. Not a backslash, because MySQL string literals would need it doubled.
     */
    protected const LIKE_ESCAPE = '!';

    /**
     * Columns the client may sort by.
     *
     * @return list<string>
     */
    abstract protected function sortable(): array;

    /**
     * Columns matched against the search term. They go into raw SQL, so they must be literals, never input.
     *
     * @return list<literal-string>
     */
    abstract protected function searchable(): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in($this->sortable())],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'between:1,'.config('journal.pagination.max_per_page', 100)],
        ];
    }

    public function search(): ?string
    {
        return $this->validated('search');
    }

    public function sort(): string
    {
        return $this->validated('sort', 'created_at');
    }

    /**
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        return $this->validated('direction', 'desc');
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', config('journal.pagination.per_page', 15));
    }

    /**
     * Apply the validated search and sort to the query and paginate it.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query): LengthAwarePaginator
    {
        return $query
            ->when(
                $this->search(),
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                    // Postgres LIKE is case-sensitive; MySQL and SQLite are not.
                    $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                    $pattern = '%'.$this->escapeLike($search).'%';

                    foreach ($this->searchable() as $column) {
                        $query->orWhereRaw("{$column} {$operator} ? escape '".self::LIKE_ESCAPE."'", [$pattern]);
                    }
                })
            )
            ->orderBy($this->sort(), $this->direction())
            // Tie-breaker so rows sharing the sort value can't repeat or go missing between pages.
            ->orderBy($query->getModel()->getQualifiedKeyName(), $this->direction())
            ->paginate($this->perPage())
            ->withQueryString();
    }

    /**
     * Make the search term match literally, so "%" and "_" are not wildcards.
     */
    protected function escapeLike(string $value): string
    {
        $escape = self::LIKE_ESCAPE;

        return str_replace([$escape, '%', '_'], [$escape.$escape, $escape.'%', $escape.'_'], $value);
    }
}
