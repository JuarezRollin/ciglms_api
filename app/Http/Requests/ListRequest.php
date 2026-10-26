<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:64'],
            'direction' => ['nullable', 'in:asc,desc'],
            'filter' => ['nullable', 'array'],
            'filter.*' => ['nullable', 'string', 'max:255'],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.config('api.pagination.max_per_page'),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
