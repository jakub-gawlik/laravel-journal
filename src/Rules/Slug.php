<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lowercase letters and digits separated by single hyphens, the same shape Str::slug() produces.
 */
class Slug implements ValidationRule
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('The :attribute may only contain lowercase letters, numbers and single hyphens.');
        }
    }
}
