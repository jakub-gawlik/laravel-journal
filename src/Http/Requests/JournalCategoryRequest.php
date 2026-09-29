<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Jgawlik\LaravelJournal\JournalServiceProvider;
use Jgawlik\LaravelJournal\Models\JournalCategory;
use Jgawlik\LaravelJournal\Rules\Slug;

class JournalCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(JournalServiceProvider::MANAGE_CATEGORIES);
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST') && blank($this->input('slug')) && filled($this->input('name'))) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $category = $this->route('category');

        return [
            'name' => [$required, 'string', 'max:255', Rule::unique(JournalCategory::class, 'name')->ignore($category)],
            'slug' => [$required, new Slug, 'max:255', Rule::unique(JournalCategory::class, 'slug')->ignore($category)],
        ];
    }
}
