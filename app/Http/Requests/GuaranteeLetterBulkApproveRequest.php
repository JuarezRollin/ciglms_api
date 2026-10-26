<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GuaranteeLetterBulkApproveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api')?->hasRole('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gl_ids' => ['required', 'array', 'min:1', 'max:100'],
            'gl_ids.*' => ['required', 'integer', 'distinct', 'exists:guarantee_letters,id'],
        ];
    }
}
