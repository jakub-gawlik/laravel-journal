<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Jgawlik\LaravelJournal\Models\JournalTag;

/**
 * @extends Factory<JournalTag>
 */
class JournalTagFactory extends Factory
{
    protected $model = JournalTag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'user_id' => fn () => config('journal.user_model')::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
