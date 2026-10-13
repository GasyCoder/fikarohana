<?php

namespace App\Http\Requests\JournalDiscovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['no_apc'] as $key) {
            if ($this->has($key) && in_array($this->input($key), ['true', 'false'], true)) {
                $this->merge([$key => $this->input($key) === 'true']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:500'],
            'subject' => ['sometimes', 'uuid', Rule::exists('subjects', 'id')],
            'language' => ['sometimes', 'string', Rule::exists('languages', 'code')],
            'article_type' => ['sometimes', 'string', Rule::exists('article_types', 'code')],
            'indexation' => ['sometimes', 'string', Rule::exists('indexation_services', 'code')],
            'publisher' => ['sometimes', 'uuid', Rule::exists('publishers', 'id')],
            'no_apc' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
