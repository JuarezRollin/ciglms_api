<?php

namespace App\Http\Requests;

class ReturnFromBudgetForComplianceRequest extends ReturnVoucherBatchForComplianceRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer', 'compliance']) ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'compliance_classification' => ['required', 'in:internal,external'],
        ];
    }
}
