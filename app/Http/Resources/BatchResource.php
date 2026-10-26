<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_id' => $this->voucher_id,
            'ors_id' => $this->ors_id,
            'dv_id' => $this->dv_id,
            'cheque_id' => $this->cheque_id,
            'total_amount' => $this->total_amount,
            'remarks' => $this->remarks,
            'reviewer_id' => $this->reviewer_id,
            'reviewer' => $this->whenLoaded('reviewer', fn (): array => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
                'position' => $this->reviewer?->position,
            ]),
            'voucher' => $this->whenLoaded('voucher'),
            'ors' => $this->whenLoaded('ors'),
            'dv' => $this->whenLoaded('dv'),
            'cheque' => $this->whenLoaded('cheque'),
            'gls' => GuaranteeLetterResource::collection($this->whenLoaded('gls')),
            'latest_resolved_compliance' => ResolvedComplianceResource::make($this->whenLoaded('latestResolvedCompliance')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
