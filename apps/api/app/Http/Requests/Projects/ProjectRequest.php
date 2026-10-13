<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->route('project') || $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'required', 'string', 'max:500'],
            'abstract' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'keywords' => ['sometimes', 'array', 'max:20'], 'keywords.*' => ['required', 'string', 'max:100', 'distinct'],
            'subject_ids' => ['sometimes', 'array', 'max:10'], 'subject_ids.*' => ['required', 'uuid', 'distinct', Rule::exists('subjects', 'id')],
            'language' => ['sometimes', 'nullable', 'string', Rule::exists('languages', 'code')],
            'article_type' => ['sometimes', 'nullable', 'string', Rule::exists('article_types', 'code')],
            'no_apc_only' => ['sometimes', 'boolean'],
            'indexation_services' => ['sometimes', 'array', 'max:10'],
            'indexation_services.*' => ['required', 'string', 'distinct', Rule::exists('indexation_services', 'code')],
            'user_id' => ['prohibited'], 'selected_journal_id' => ['prohibited'], 'journal_selected_at' => ['prohibited'],
        ];
    }
}
