<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Jgawlik\LaravelJournal\Models\JournalCategory;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Personal',
            'Work',
            'Travel',
            'Ideas',
            'Learning',
        ];

        foreach ($categories as $name) {
            JournalCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
