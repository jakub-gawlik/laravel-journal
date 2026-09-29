<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jgawlik\LaravelJournal\Models\JournalPost;

/**
 * @extends Factory<JournalPost>
 */
class JournalPostFactory extends Factory
{
    protected $model = JournalPost::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => config('journal.user_model')::factory(),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
        ];
    }
}
