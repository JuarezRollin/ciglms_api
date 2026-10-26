<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordVoucherFinancialDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'ors_no' => ['nullable', 'string', 'max:100'],
            'dv_no' => ['nullable', 'string', 'max:100'],
            'cheque_no' => ['nullable', 'string', 'max:100', 'required_with:cheque_date'],
            'cheque_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'required_with:cheque_no'],
        ];
    }
}
