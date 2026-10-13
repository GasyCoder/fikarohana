<?php

namespace App\Http\Requests\JournalDiscovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['sometimes', 'string', 'max:10000'],
            'keywords' => ['sometimes', 'array', 'max:20'],
            'keywords.*' => ['required', 'string', 'max:100', 'distinct'],
            'subject_ids' => ['sometimes', 'array', 'max:10'],
            'subject_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('subjects', 'id')],
            'language' => ['sometimes', 'string', Rule::exists('languages', 'code')],
            'article_type' => ['sometimes', 'string', Rule::exists('article_types', 'code')],
            'no_apc_only' => ['sometimes', 'boolean'],
            'indexation_services' => ['sometimes', 'array', 'max:10'],
            'indexation_services.*' => ['required', 'string', 'distinct', Rule::exists('indexation_services', 'code')],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
