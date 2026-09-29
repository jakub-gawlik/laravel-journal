<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Jgawlik\LaravelJournal\Models\JournalTag;
use Jgawlik\LaravelJournal\Rules\Slug;

class JournalTagRequest extends FormRequest
{
    public function authorize(): Response
    {
        $tag = $this->route('tag');

        return $tag instanceof JournalTag
            ? Gate::inspect('update', $tag)
            : Gate::inspect('create', JournalTag::class);
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

        return [
            'name' => [$required, 'string', 'max:50'],
            'slug' => [
                $required, new Slug, 'max:255',
                Rule::unique(JournalTag::class, 'slug')
                    ->where('user_id', $this->user()->getAuthIdentifier())
                    ->ignore($this->route('tag')),
            ],
        ];
    }
}
