<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
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
            'service_provider_id' => ['required', 'integer', 'exists:service_providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'branch_salutation' => ['nullable', 'string', 'max:255'],
            'branch_addressee' => ['nullable', 'string', 'max:255'],
            'branch_designation' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
