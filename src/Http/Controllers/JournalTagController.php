<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Jgawlik\LaravelJournal\Http\Requests\JournalTagIndexRequest;
use Jgawlik\LaravelJournal\Http\Requests\JournalTagRequest;
use Jgawlik\LaravelJournal\Http\Resources\JournalTagResource;
use Jgawlik\LaravelJournal\Models\JournalTag;

class JournalTagController extends Controller
{
    public function index(JournalTagIndexRequest $request): AnonymousResourceCollection
    {
        $tags = $request->paginate(
            JournalTag::query()->ownedBy($request->user())->withCount('posts')
        );

        return JournalTagResource::collection($tags);
    }

    public function store(JournalTagRequest $request): JsonResponse
    {
        try {
            $tag = JournalTag::create([
                ...$request->validated(),
                'user_id' => $request->user()->getAuthIdentifier(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $this->slugTaken();
        }

        return JournalTagResource::make($tag)
            ->response()
            ->setStatusCode(201);
    }

    public function show(JournalTag $tag): JournalTagResource
    {
        Gate::authorize('view', $tag);

        return JournalTagResource::make($tag->loadCount('posts'));
    }

    public function update(JournalTagRequest $request, JournalTag $tag): JournalTagResource
    {
        try {
            $tag->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw $this->slugTaken();
        }

        return JournalTagResource::make($tag);
    }

    public function destroy(JournalTag $tag): Response
    {
        Gate::authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }

    /**
     * The (user_id, slug) index rejected a slug that passed validation, because a concurrent
     * request took it first.
     */
    protected function slugTaken(): ValidationException
    {
        return ValidationException::withMessages([
            'slug' => __('validation.unique', ['attribute' => 'slug']),
        ]);
    }
}
