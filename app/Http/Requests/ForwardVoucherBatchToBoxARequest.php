<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForwardVoucherBatchToBoxARequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_fwd_to_dc' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
