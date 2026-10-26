<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForwardVoucherBatchToBoxCRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer', 'outgoing', 'compliance']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ors_no' => ['prohibited'],
            'date_fwd_to_actg' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
