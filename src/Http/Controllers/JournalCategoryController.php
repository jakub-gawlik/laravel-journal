<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Controllers;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Jgawlik\LaravelJournal\Http\Requests\JournalCategoryIndexRequest;
use Jgawlik\LaravelJournal\Http\Requests\JournalCategoryRequest;
use Jgawlik\LaravelJournal\Http\Resources\JournalCategoryResource;
use Jgawlik\LaravelJournal\JournalServiceProvider;
use Jgawlik\LaravelJournal\Models\JournalCategory;
use Jgawlik\LaravelJournal\Models\JournalPost;

class JournalCategoryController extends Controller
{
    public function index(JournalCategoryIndexRequest $request): AnonymousResourceCollection
    {
        $categories = $request->paginate(
            JournalCategory::query()->withCount($this->ownPostsCount($request->user()))
        );

        return JournalCategoryResource::collection($categories);
    }

    public function store(JournalCategoryRequest $request): JsonResponse
    {
        try {
            $category = JournalCategory::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw $this->taken($request->validated());
        }

        return JournalCategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, JournalCategory $category): JournalCategoryResource
    {
        return JournalCategoryResource::make($category->loadCount($this->ownPostsCount($request->user())));
    }

    public function update(JournalCategoryRequest $request, JournalCategory $category): JournalCategoryResource
    {
        try {
            $category->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw $this->taken($request->validated(), $category);
        }

        return JournalCategoryResource::make($category);
    }

    public function destroy(JournalCategory $category): Response
    {
        Gate::authorize(JournalServiceProvider::MANAGE_CATEGORIES);

        $category->delete();

        return response()->noContent();
    }

    /**
     * A unique index rejected a name or slug that passed validation, because a concurrent
     * request took it first. Report the error on whichever of the two is now taken.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function taken(array $attributes, ?JournalCategory $category = null): ValidationException
    {
        $field = collect(['name', 'slug'])->first(
            fn (string $field) => isset($attributes[$field]) && JournalCategory::query()
                ->where($field, $attributes[$field])
                ->when($category, fn (Builder $query, JournalCategory $category) => $query->whereKeyNot($category->getKey()))
                ->exists()
        ) ?? 'slug';

        return ValidationException::withMessages([
            $field => __('validation.unique', ['attribute' => $field]),
        ]);
    }

    /**
     * Categories are shared between users, so only count the viewer's own posts.
     *
     * The ownedBy scope is built on a typed JournalPost query and merged in, because the
     * withCount closure only receives an untyped Builder.
     *
     * @return array<string, Closure>
     */
    protected function ownPostsCount(Authenticatable $user): array
    {
        $owned = JournalPost::query()->ownedBy($user);

        return ['posts' => fn (Builder $query) => $query->mergeConstraintsFrom($owned)];
    }
}
