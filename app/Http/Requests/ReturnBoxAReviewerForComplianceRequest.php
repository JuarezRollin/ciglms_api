<?php

namespace App\Http\Requests;

class ReturnBoxAReviewerForComplianceRequest extends ReturnVoucherBatchForComplianceRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->hasAnyRole(['admin', 'reviewer', 'box a reviewer']) ?? false;
    }
}
