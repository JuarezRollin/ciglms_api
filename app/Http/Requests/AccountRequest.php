<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($this->route('account'))],
            'position' => ['nullable', 'string', 'max:255'],
            'initials' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN]+$/u'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->route('account'))],
            'password' => [$this->isMethod('post') ? 'required' : 'sometimes', 'max:50', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => [$this->isMethod('post') ? 'required' : 'required_with:password'],
        ];
    }
}
