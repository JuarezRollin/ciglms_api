<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->resource['user'],
            'compliance_open_total' => $this->resource['compliance_open_total'],
            'review_voucher_count' => $this->resource['review_voucher_count'],
            'outgoing_voucher_counts' => $this->resource['outgoing_voucher_counts'],
            'stats' => $this->resource['stats'],
            'summary' => $this->resource['summary'],
            'monthly_assistance_trend' => $this->resource['monthly_assistance_trend'],
            'processing_status' => $this->resource['processing_status'],
            'assistance_types' => $this->resource['assistance_types'],
            'pending_actions' => $this->resource['pending_actions'],
        ];
    }
}
