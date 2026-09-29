<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Jgawlik\LaravelJournal\Models\JournalCategory;

/**
 * @extends Factory<JournalCategory>
 */
class JournalCategoryFactory extends Factory
{
    protected $model = JournalCategory::class;

    public function definition(): array
    {
        $name = Str::title(implode(' ', (array) $this->faker->unique()->words(2)));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
