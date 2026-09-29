<?php

use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Models\JournalTag;
use Jgawlik\LaravelJournal\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

it('requires authentication', function () {
    $this->getJson('/api/journal/tags')->assertUnauthorized();
});

it('lists only the user\'s tags', function () {
    JournalTag::factory()->for($this->user, 'user')->create(['slug' => 'mine']);
    JournalTag::factory()->for($this->other, 'user')->create(['slug' => 'theirs']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/tags')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'mine');
});

it('searches only the user\'s tags', function () {
    JournalTag::factory()->for($this->user, 'user')->create(['name' => 'Laravel', 'slug' => 'laravel']);
    JournalTag::factory()->for($this->user, 'user')->create(['name' => 'Travel', 'slug' => 'travel']);
    JournalTag::factory()->for($this->other, 'user')->create(['name' => 'Laravel', 'slug' => 'laravel']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/tags?search=lara')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'laravel');
});

it('resolves a shared slug to the current user\'s tag', function () {
    JournalTag::factory()->for($this->other, 'user')->create(['name' => 'Theirs', 'slug' => 'same']);
    JournalTag::factory()->for($this->user, 'user')->create(['name' => 'Mine', 'slug' => 'same']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/tags/same')
        ->assertOk()
        ->assertJsonPath('data.name', 'Mine');
});

it('includes how many posts use each tag', function () {
    $tag = JournalTag::factory()->for($this->user, 'user')->create(['slug' => 'diary']);
    $tag->posts()->attach(JournalPost::factory()->count(2)->for($this->user, 'user')->create());

    $this->actingAs($this->user)
        ->getJson('/api/journal/tags')
        ->assertJsonPath('data.0.posts_count', 2);

    $this->actingAs($this->user)
        ->getJson('/api/journal/tags/diary')
        ->assertJsonPath('data.posts_count', 2);
});

it('rejects a tag slug that is not lowercase and hyphenated', function () {
    $this->actingAs($this->user)
        ->postJson('/api/journal/tags', ['name' => 'Road Trip', 'slug' => 'Road_Trip'])
        ->assertJsonValidationErrors('slug');
});

it('creates, updates and deletes a tag', function () {
    $this->actingAs($this->user)
        ->postJson('/api/journal/tags', ['name' => 'Road Trip'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'road-trip');

    $this->actingAs($this->user)
        ->patchJson('/api/journal/tags/road-trip', ['name' => 'Roadtrip'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Roadtrip');

    $this->actingAs($this->user)
        ->deleteJson('/api/journal/tags/road-trip')
        ->assertNoContent();
});

it('cannot touch another user\'s tag', function () {
    JournalTag::factory()->for($this->other, 'user')->create(['slug' => 'private']);

    $this->actingAs($this->user)->getJson('/api/journal/tags/private')->assertNotFound();
    $this->actingAs($this->user)->patchJson('/api/journal/tags/private', ['name' => 'x'])->assertNotFound();
    $this->actingAs($this->user)->deleteJson('/api/journal/tags/private')->assertNotFound();

    expect(JournalTag::where('slug', 'private')->exists())->toBeTrue();
});

it('reports a lost race on a new tag slug as a validation error', function () {
    JournalTag::creating(function (JournalTag $tag) {
        JournalTag::withoutEvents(fn () => JournalTag::factory()->for($this->user, 'user')->create(['slug' => $tag->slug]));
    });

    $this->actingAs($this->user)
        ->postJson('/api/journal/tags', ['name' => 'Road Trip'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('reports a lost race on an updated tag slug as a validation error', function () {
    JournalTag::factory()->for($this->user, 'user')->create(['slug' => 'diary']);

    JournalTag::updating(function (JournalTag $tag) {
        JournalTag::withoutEvents(fn () => JournalTag::factory()->for($this->user, 'user')->create(['slug' => $tag->slug]));
    });

    $this->actingAs($this->user)
        ->patchJson('/api/journal/tags/diary', ['slug' => 'journal'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});
