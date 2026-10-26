<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ListGlTransmittalRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'scope' => ['nullable', 'in:receiving'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'received_by' => ['nullable', 'integer', 'exists:users,id'],
            'receipt_status' => ['nullable', 'in:pending,partial,received,cancelled'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
