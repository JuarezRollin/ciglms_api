<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class GuaranteeLetterReviewListRequest extends ListRequest
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
            'filter.service_provider_id' => ['nullable', 'integer', 'min:1'],
            'filter.signatory_id' => ['nullable', 'integer', 'min:1'],
            'filter.category_id' => ['nullable', 'integer', 'min:1'],
            'filter.created_by' => ['nullable', 'integer', 'min:1'],
            'filter.swo_id' => ['nullable', 'integer', 'min:1'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
        ];
    }
}
