<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveGlTransmittalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'glc superadmin', 'glc admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'gl_ids' => ['required', 'array', 'min:1', 'max:30'],
            'gl_ids.*' => ['required', 'integer', 'distinct', 'exists:guarantee_letters,id'],
        ];
    }
}
