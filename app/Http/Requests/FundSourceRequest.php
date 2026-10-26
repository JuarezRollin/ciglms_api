<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundSourceRequest extends FormRequest
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
        $this->merge([
            'amount' => $this->amount !== null
                ? str_replace(',', '', $this->amount)
                : null,
        ]);
        
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('fund_sources')->ignore($this->route('fund_source'))],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
