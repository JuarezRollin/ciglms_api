<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_kind' => $this->batch_id !== null ? 'voucher' : 'gl',
            'compliance_type' => $this->whenLoaded('complianceType', fn (): array => [
                'id' => $this->complianceType->id,
                'name' => $this->complianceType->name,
            ]),
            'returned_at' => $this->returned_at,
            'compliance_classification' => $this->compliance_classification,
            'remarks' => $this->remarks,
            'gl' => GuaranteeLetterResource::make($this->whenLoaded('guaranteeLetter')),
            'voucher_compliance_records' => $this->whenLoaded('voucherComplianceRecords', fn (): array => $this->voucherComplianceRecords
                ->map(fn ($record): array => [
                    'id' => $record->id,
                    'remarks' => $record->remarks,
                    'returned_at' => $record->returned_at,
                    'resolved_at' => $record->resolved_at,
                    'gl' => GuaranteeLetterResource::make(
                        $record->relationLoaded('guaranteeLetter') ? $record->guaranteeLetter : null,
                    ),
                ])
                ->all()),
            'batch' => VoucherBatchResource::make($this->whenLoaded('batch')),
        ];
    }
}
