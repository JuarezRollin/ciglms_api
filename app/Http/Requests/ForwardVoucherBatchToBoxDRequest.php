<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForwardVoucherBatchToBoxDRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer', 'outgoing', 'compliance']) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'dv_no' => ['prohibited'],
            'date_fwd_to_dir_dv' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
