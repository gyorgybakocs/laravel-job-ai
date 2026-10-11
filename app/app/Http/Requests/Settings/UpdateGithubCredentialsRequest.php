<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGithubCredentialsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'github_username' => [
                'nullable',
                'string',
                'max:39',
                'regex:/\A(?!-)[A-Za-z0-9-]+(?<!-)\z/',
            ],
            'github_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
