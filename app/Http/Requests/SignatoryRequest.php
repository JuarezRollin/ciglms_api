<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignatoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('signatories')->ignore($this->route('signatory'))],
            'initials' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN]+$/u'],
            'designation' => ['required', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'gte:minimum_amount'],
            'is_active' => ['sometimes', 'boolean'],
            'p12_file' => ['nullable', 'file', 'extensions:p12', 'max:5120'],
            'signature_image' => ['nullable', 'image', 'mimes:png', 'extensions:png', 'max:5120'],
            'pnpki_password' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
