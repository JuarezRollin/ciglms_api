<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVoucherBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'voucher creation']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gl_ids' => ['required', 'array', 'min:1', 'max:10'],
            'gl_ids.*' => ['required', 'integer', 'distinct', 'exists:guarantee_letters,id'],
            'fund_source_id' => [
                'required',
                'integer',
                Rule::exists('fund_sources', 'id')->where('is_active', true),
            ],
            'reviewer_id' => [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! User::query()->role('reviewer')->whereKey($value)->exists()) {
                        $fail('Select a reviewer.');
                    }
                },
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
