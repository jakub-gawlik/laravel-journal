<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jgawlik\LaravelJournal\Http\Requests\JournalPostIndexRequest;
use Jgawlik\LaravelJournal\Http\Requests\JournalPostRequest;
use Jgawlik\LaravelJournal\Http\Resources\JournalPostResource;
use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Models\JournalTag;
use Throwable;

class JournalPostController extends Controller
{
    public function index(JournalPostIndexRequest $request): AnonymousResourceCollection
    {
        $query = JournalPost::query()
            ->ownedBy($request->user())
            ->with(['category', 'tags'])
            ->when(
                $request->validated('category'),
                fn (Builder $query, string $category) => $query->whereRelation('category', 'slug', $category)
            )
            ->when(
                $request->validated('tag'),
                fn (Builder $query, string $tag) => $query->whereRelation('tags', 'slug', $tag)
            );

        $posts = $request->paginate($query);

        return JournalPostResource::collection($posts);
    }

    public function store(JournalPostRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            // A concurrent request can take the generated slug between the uniqueness check and
            // the insert. Retrying lets uniqueSlugFor() pick the next free one.
            $post = retry(3, fn () => DB::transaction(function () use ($request, $user) {
                $post = JournalPost::create([
                    ...$request->safe()->except('tags'),
                    'user_id' => $user->getAuthIdentifier(),
                ]);

                $post->tags()->sync($this->resolveTagIds($user, $request->tags()));

                return $post;
            }), when: fn (Throwable $e) => $e instanceof UniqueConstraintViolationException && ! $request->has('slug'));
        } catch (UniqueConstraintViolationException) {
            throw $this->slugTaken();
        }

        return JournalPostResource::make($post->load(['category', 'tags']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(JournalPost $post): JournalPostResource
    {
        Gate::authorize('view', $post);

        return JournalPostResource::make($post->load(['category', 'tags']));
    }

    public function update(JournalPostRequest $request, JournalPost $post): JournalPostResource
    {
        try {
            DB::transaction(function () use ($request, $post) {
                $post->update($request->safe()->except('tags'));

                if ($request->has('tags')) {
                    $post->tags()->sync($this->resolveTagIds($request->user(), $request->tags()));
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->slugTaken();
        }

        return JournalPostResource::make($post->load(['category', 'tags']));
    }

    public function destroy(JournalPost $post): Response
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }

    /**
     * Find or create the user's tags for the given names.
     *
     * Existing tags are found in one query. Missing ones are inserted in one more, ignoring any
     * that a concurrent request created first, and then read back.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    protected function resolveTagIds(Authenticatable $user, array $names): array
    {
        $names = collect($names)
            ->map(fn (string $name) => trim($name))
            ->keyBy(fn (string $name) => Str::slug($name))
            ->reject(fn (string $name, string $slug) => $slug === '');

        if ($names->isEmpty()) {
            return [];
        }

        $ids = $this->tagIdsBySlug($user, $names->keys()->all());
        $missing = $names->diffKeys($ids);

        if ($missing->isNotEmpty()) {
            $now = (new JournalTag)->freshTimestampString();

            JournalTag::query()->insertOrIgnore($missing->map(fn (string $name, string $slug) => [
                'user_id' => $user->getAuthIdentifier(),
                'name' => $name,
                'slug' => (string) $slug,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());

            $ids = $ids->union($this->tagIdsBySlug($user, $missing->keys()->all()));
        }

        return $ids->values()->all();
    }

    /**
     * @param  array<int, int|string>  $slugs
     * @return Collection<array-key, int>
     */
    protected function tagIdsBySlug(Authenticatable $user, array $slugs): Collection
    {
        return JournalTag::query()
            ->ownedBy($user)
            ->whereIn('slug', array_map('strval', $slugs))
            ->pluck('id', 'slug');
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
