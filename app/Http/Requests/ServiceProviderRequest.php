<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceProviderRequest extends FormRequest
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
            'region_id' => ['nullable', 'integer', Rule::exists('psgc_regions', 'reg_id')],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'salutation' => ['nullable', 'string', 'max:255'],
            'addressee' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'sp_remarks' => ['nullable', 'string'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'moa' => ['sometimes', 'boolean'],
            'jurisdiction' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
