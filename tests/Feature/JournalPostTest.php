<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Jgawlik\LaravelJournal\Models\JournalCategory;
use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Models\JournalTag;
use Jgawlik\LaravelJournal\Rules\Slug;
use Jgawlik\LaravelJournal\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

it('requires authentication', function () {
    $this->getJson('/api/journal/posts')->assertUnauthorized();
});

it('lets any authenticated user list their posts via the viewAny ability', function () {
    expect(Gate::forUser($this->user)->allows('viewAny', JournalPost::class))->toBeTrue();
});

it('lists only the current user\'s posts, paginated', function () {
    JournalPost::factory()->count(3)->for($this->user, 'user')->create();
    JournalPost::factory()->count(2)->for($this->other, 'user')->create();

    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('filters by category and tag', function () {
    $work = JournalCategory::factory()->create(['slug' => 'work']);
    $inWork = JournalPost::factory()->for($this->user, 'user')->create(['category_id' => $work->id]);
    JournalPost::factory()->for($this->user, 'user')->create();

    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?category=work')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inWork->id);

    // An unknown category matches nothing (not the uncategorised posts).
    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?category=missing')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $tag = JournalTag::factory()->for($this->user, 'user')->create(['slug' => 'ideas']);
    $inWork->tags()->attach($tag);

    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?tag=ideas')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inWork->id);
});

it('searches the user\'s posts by title and content', function () {
    $byTitle = JournalPost::factory()->for($this->user, 'user')->create(['title' => 'Trip to Kraków', 'content' => 'Nice']);
    $byContent = JournalPost::factory()->for($this->user, 'user')->create(['title' => 'Day two', 'content' => 'Still in Kraków']);
    JournalPost::factory()->for($this->user, 'user')->create(['title' => 'Home', 'content' => 'Back home']);
    JournalPost::factory()->for($this->other, 'user')->create(['title' => 'Kraków too', 'content' => 'Theirs']);

    $ids = $this->actingAs($this->user)
        ->getJson('/api/journal/posts?search=krak')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$byTitle->id, $byContent->id]);
});

it('treats LIKE wildcards in the search term literally', function () {
    $match = JournalPost::factory()->for($this->user, 'user')->create(['title' => '100% done_ok!', 'content' => 'x']);
    JournalPost::factory()->for($this->user, 'user')->create(['title' => '100 things', 'content' => 'doneXok']);

    foreach (['%', '_', '!', '100%', 'done_ok!'] as $search) {
        $this->actingAs($this->user)
            ->getJson('/api/journal/posts?search='.urlencode($search))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }
});

it('searches case-insensitively', function () {
    $post = JournalPost::factory()->for($this->user, 'user')->create(['title' => 'Summer Holiday']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?search=SUMMER')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $post->id);
});

it('breaks sort ties by id so pagination is stable', function () {
    // SQLite happens to return ties in id order anyway; MySQL and Postgres don't, so assert the SQL.
    JournalPost::factory()->for($this->user, 'user')->create();
    DB::enableQueryLog();

    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?sort=title&direction=asc')
        ->assertOk();

    expect(collect(DB::getQueryLog())->pluck('query')->implode("\n"))
        ->toContain('order by "title" asc, "journal_posts"."id" asc');
});

it('validates index query parameters', function () {
    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?sort=password&direction=up&per_page=1000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort', 'direction', 'per_page']);
});

it('creates a post with tags scoped to the user', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', [
            'title' => 'My first day',
            'content' => 'Hello journal',
            'tags' => ['Laravel', 'laravel ', 'PHP', '!!!'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'my-first-day')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonCount(2, 'data.tags');

    expect(JournalTag::where('user_id', $this->user->id)->pluck('slug')->sort()->values()->all())
        ->toBe(['laravel', 'php']);
});

it('generates unique slugs per user for duplicate titles', function () {
    $payload = ['title' => 'Same title', 'content' => 'x'];

    $this->actingAs($this->user)->postJson('/api/journal/posts', $payload)->assertJsonPath('data.slug', 'same-title');
    $this->actingAs($this->user)->postJson('/api/journal/posts', $payload)->assertJsonPath('data.slug', 'same-title-2');
    $this->actingAs($this->other)->postJson('/api/journal/posts', $payload)->assertJsonPath('data.slug', 'same-title');
});

it('ignores a user_id in the payload', function () {
    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'user_id' => $this->other->id])
        ->assertCreated();

    expect(JournalPost::sole()->isOwnedBy($this->user))->toBeTrue();
});

it('returns the category id alongside the category', function () {
    $category = JournalCategory::factory()->create();

    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'category_id' => $category->id])
        ->assertCreated()
        ->assertJsonPath('data.category_id', $category->id)
        ->assertJsonPath('data.category.slug', $category->slug);
});

it('only accepts lowercase hyphenated slugs', function (string $slug, bool $valid) {
    $response = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'slug' => $slug]);

    $valid ? $response->assertCreated() : $response->assertJsonValidationErrors('slug');
})->with([
    'lowercase with hyphens' => ['my-first-day-2', true],
    'uppercase' => ['My-Slug', false],
    'underscore' => ['my_slug', false],
    'double hyphen' => ['my--slug', false],
    'leading hyphen' => ['-my-slug', false],
    'trailing hyphen' => ['my-slug-', false],
]);

it('only accepts http and https cover image urls', function (string $url, bool $valid) {
    $response = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'cover_image' => $url]);

    $valid ? $response->assertCreated() : $response->assertJsonValidationErrors('cover_image');
})->with([
    'https' => ['https://example.com/images/cover.jpg?w=1200', true],
    'http' => ['http://example.com/cover.png', true],
    'long url' => ['https://example.com/'.str_repeat('a', 1500), true],
    'too long' => ['https://example.com/'.str_repeat('a', 2100), false],
    'not a url' => ['cover.jpg', false],
    'javascript' => ['javascript:alert(1)', false],
    'ftp' => ['ftp://example.com/cover.jpg', false],
]);

it('shows, updates and deletes the owner\'s post', function () {
    $post = JournalPost::factory()->for($this->user, 'user')->create(['title' => 'Before']);

    $this->actingAs($this->user)
        ->getJson("/api/journal/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Before');

    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['title' => 'After', 'tags' => ['diary']])
        ->assertOk()
        ->assertJsonPath('data.title', 'After')
        ->assertJsonPath('data.tags.0.slug', 'diary');

    $this->actingAs($this->user)
        ->deleteJson("/api/journal/posts/{$post->id}")
        ->assertNoContent();

    expect(JournalPost::find($post->id))->toBeNull();
});

it('hides other users\' posts with a 404', function () {
    $post = JournalPost::factory()->for($this->other, 'user')->create();

    $this->actingAs($this->user)->getJson("/api/journal/posts/{$post->id}")->assertNotFound();
    $this->actingAs($this->user)->patchJson("/api/journal/posts/{$post->id}", ['title' => 'x'])->assertNotFound();
    $this->actingAs($this->user)->deleteJson("/api/journal/posts/{$post->id}")->assertNotFound();

    expect($post->fresh()->title)->not->toBe('x');
});

it('rejects a slug already used by the same user', function () {
    JournalPost::factory()->for($this->user, 'user')->create(['slug' => 'taken']);
    $post = JournalPost::factory()->for($this->user, 'user')->create();

    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['slug' => 'taken'])
        ->assertJsonValidationErrors('slug');

    // Keeping its own slug is fine.
    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['slug' => $post->slug])
        ->assertOk();
});

it('rejects a category that does not exist', function () {
    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'category_id' => 999])
        ->assertJsonValidationErrors('category_id');
});

it('removes all tags when tags is an empty list or null', function (?array $tags) {
    $post = JournalPost::factory()->for($this->user, 'user')->create();
    $post->tags()->attach(JournalTag::factory()->count(2)->for($this->user, 'user')->create());

    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['tags' => $tags])
        ->assertOk()
        ->assertJsonCount(0, 'data.tags');

    expect($post->tags()->count())->toBe(0)
        ->and(JournalTag::count())->toBe(2);
})->with([
    'empty list' => [[]],
    'null' => [null],
]);

it('keeps tags when the tags field is left out of an update', function () {
    $post = JournalPost::factory()->for($this->user, 'user')->create();
    $post->tags()->attach(JournalTag::factory()->for($this->user, 'user')->create());

    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['title' => 'Renamed'])
        ->assertJsonCount(1, 'data.tags');
});

it('reuses the user\'s existing tags without renaming them', function () {
    $existing = JournalTag::factory()->for($this->user, 'user')->create(['name' => 'Road Trip', 'slug' => 'road-trip']);
    $theirs = JournalTag::factory()->for($this->other, 'user')->create(['name' => 'Poland', 'slug' => 'poland']);

    $tags = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'tags' => ['road trip', 'Poland', '2024']])
        ->assertCreated()
        ->json('data.tags');

    expect(collect($tags)->pluck('name', 'slug')->sortKeys()->all())
        ->toBe(['2024' => '2024', 'poland' => 'Poland', 'road-trip' => 'Road Trip'])
        ->and(collect($tags)->pluck('id'))->toContain($existing->id)->not->toContain($theirs->id)
        ->and(JournalTag::ownedBy($this->user)->count())->toBe(3);
});

it('resolves many tags in a fixed number of queries', function () {
    JournalTag::factory()->for($this->user, 'user')->create(['name' => 'existing', 'slug' => 'existing']);
    $names = ['existing', ...array_map(fn (int $i) => "new tag {$i}", range(1, 15))];

    DB::enableQueryLog();

    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'tags' => $names])
        ->assertCreated()
        ->assertJsonCount(16, 'data.tags');

    // Look up existing, insert missing, read them back, then eager-load for the response.
    $tagQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, '"journal_tags"'));

    expect($tagQueries)->toHaveCount(4);
});

it('retries with the next free slug when a concurrent request takes the generated one', function () {
    $attempts = 0;

    // Boot the model first so its slug-generating listener runs before this one.
    new JournalPost;

    // Simulate the race: another post takes the slug between the uniqueness check and the insert.
    JournalPost::creating(function (JournalPost $post) use (&$attempts) {
        if (++$attempts === 1) {
            JournalPost::withoutEvents(fn () => JournalPost::factory()->for($this->user, 'user')->create(['slug' => $post->slug]));
        }
    });

    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'Same title', 'content' => 'C'])
        ->assertCreated();

    expect($attempts)->toBe(2);
});

it('reports a lost race on a client-chosen slug as a validation error', function () {
    JournalPost::creating(function (JournalPost $post) {
        JournalPost::withoutEvents(fn () => JournalPost::factory()->for($this->user, 'user')->create(['slug' => $post->slug]));
    });

    $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => 'T', 'content' => 'C', 'slug' => 'my-slug'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('reports a lost race on an updated slug as a validation error', function () {
    $post = JournalPost::factory()->for($this->user, 'user')->create();

    JournalPost::updating(function (JournalPost $post) {
        JournalPost::withoutEvents(fn () => JournalPost::factory()->for($this->user, 'user')->create(['slug' => $post->slug]));
    });

    $this->actingAs($this->user)
        ->patchJson("/api/journal/posts/{$post->id}", ['slug' => 'new-slug'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('never leaves a trailing hyphen when shortening a long generated slug', function () {
    // The slug of this title has a hyphen right at the 240-character cut.
    $title = str_repeat('abcd ', 51);

    $first = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => $title, 'content' => 'C'])
        ->assertCreated()
        ->json('data.slug');

    $second = $this->actingAs($this->user)
        ->postJson('/api/journal/posts', ['title' => $title, 'content' => 'C'])
        ->assertCreated()
        ->json('data.slug');

    expect($first)->toMatch(Slug::PATTERN)->toHaveLength(239)
        ->and($second)->toBe("{$first}-2")->toMatch(Slug::PATTERN);
});

it('only accepts slugs for the category and tag filters', function () {
    $this->actingAs($this->user)
        ->getJson('/api/journal/posts?category=Not_A_Slug&tag=Also%20Not')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category', 'tag']);
});
