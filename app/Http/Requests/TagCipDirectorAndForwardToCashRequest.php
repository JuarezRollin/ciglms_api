<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TagCipDirectorAndForwardToCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'box a reviewer']) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'date_fwd_to_dir_fs' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
