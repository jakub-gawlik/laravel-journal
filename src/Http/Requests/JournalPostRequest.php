<?php

declare(strict_types=1);

namespace Jgawlik\LaravelJournal\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Jgawlik\LaravelJournal\Models\JournalCategory;
use Jgawlik\LaravelJournal\Models\JournalPost;
use Jgawlik\LaravelJournal\Rules\Slug;

class JournalPostRequest extends FormRequest
{
    public function authorize(): Response
    {
        $post = $this->route('post');

        return $post instanceof JournalPost
            ? Gate::inspect('update', $post)
            : Gate::inspect('create', JournalPost::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', new Slug, 'max:255',
                Rule::unique(JournalPost::class, 'slug')
                    ->where('user_id', $this->user()->getAuthIdentifier())
                    ->ignore($this->route('post')),
            ],
            'content' => [$required, 'string'],
            'cover_image' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists(JournalCategory::class, 'id')],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return $this->validated('tags') ?? [];
    }
}
