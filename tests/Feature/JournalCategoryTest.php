<?php

use Illuminate\Support\Facades\Gate;
use Jgawlik\LaravelJournal\JournalServiceProvider;
use Jgawlik\LaravelJournal\Models\JournalCategory;
use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires authentication', function () {
    $this->getJson('/api/journal/categories')->assertUnauthorized();
});

it('lists and searches categories', function () {
    JournalCategory::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    JournalCategory::factory()->create(['name' => 'Work', 'slug' => 'work']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/categories?search=trav')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'travel');
});

it('counts only the current user\'s posts in each category', function () {
    $other = User::factory()->create();
    $category = JournalCategory::factory()->create(['slug' => 'travel']);
    JournalPost::factory()->count(2)->for($this->user, 'user')->create(['category_id' => $category->id]);
    JournalPost::factory()->count(3)->for($other, 'user')->create(['category_id' => $category->id]);

    $this->actingAs($this->user)
        ->getJson('/api/journal/categories')
        ->assertJsonPath('data.0.posts_count', 2);

    $this->actingAs($this->user)
        ->getJson('/api/journal/categories/travel')
        ->assertJsonPath('data.posts_count', 2);
});

it('shows a category by slug', function () {
    JournalCategory::factory()->create(['name' => 'Travel', 'slug' => 'travel']);

    $this->actingAs($this->user)
        ->getJson('/api/journal/categories/travel')
        ->assertOk()
        ->assertJsonPath('data.name', 'Travel');
});

it('forbids managing categories by default', function () {
    $category = JournalCategory::factory()->create();

    $this->actingAs($this->user)->postJson('/api/journal/categories', ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->user)->patchJson("/api/journal/categories/{$category->slug}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/api/journal/categories/{$category->slug}")->assertForbidden();
});

it('lets authorised users manage categories', function () {
    Gate::define(JournalServiceProvider::MANAGE_CATEGORIES, fn () => true);

    $this->actingAs($this->user)
        ->postJson('/api/journal/categories', ['name' => 'Deep Work'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'deep-work');

    $this->actingAs($this->user)
        ->patchJson('/api/journal/categories/deep-work', ['name' => 'Focus'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Focus');

    $this->actingAs($this->user)
        ->deleteJson('/api/journal/categories/deep-work')
        ->assertNoContent();

    expect(JournalCategory::count())->toBe(0);
});

it('uncategorises posts when their category is deleted', function () {
    Gate::define(JournalServiceProvider::MANAGE_CATEGORIES, fn () => true);
    $category = JournalCategory::factory()->create(['slug' => 'travel']);
    $post = JournalPost::factory()->for($this->user, 'user')->create(['category_id' => $category->id]);

    $this->actingAs($this->user)->deleteJson('/api/journal/categories/travel')->assertNoContent();

    expect($post->fresh())->not->toBeNull()
        ->and($post->fresh()->category_id)->toBeNull();
});

it('reports a lost race on a new category as a validation error on the taken field', function (string $field) {
    Gate::define(JournalServiceProvider::MANAGE_CATEGORIES, fn () => true);

    JournalCategory::creating(function (JournalCategory $category) use ($field) {
        JournalCategory::withoutEvents(fn () => JournalCategory::factory()->create([$field => $category->{$field}]));
    });

    $this->actingAs($this->user)
        ->postJson('/api/journal/categories', ['name' => 'Deep Work'])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);
})->with(['name', 'slug']);

it('reports a lost race on an updated category as a validation error', function () {
    Gate::define(JournalServiceProvider::MANAGE_CATEGORIES, fn () => true);
    JournalCategory::factory()->create(['name' => 'Travel', 'slug' => 'travel']);

    JournalCategory::updating(function (JournalCategory $category) {
        JournalCategory::withoutEvents(fn () => JournalCategory::factory()->create(['slug' => $category->slug]));
    });

    $this->actingAs($this->user)
        ->patchJson('/api/journal/categories/travel', ['slug' => 'trips'])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('slug');
});
