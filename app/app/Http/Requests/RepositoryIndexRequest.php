<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepositoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(config('repositories.statuses'))],
            'search' => ['nullable', 'string', 'max:255'],
            'search_in' => ['sometimes', Rule::in(config('repositories.search_fields'))],
            'sort' => ['sometimes', Rule::in(config('repositories.sorts'))],
            'languages' => ['sometimes', 'array', 'max:100'],
            'languages.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }
}
