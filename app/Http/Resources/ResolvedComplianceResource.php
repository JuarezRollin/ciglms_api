<?php

namespace App\Http\Resources;

use App\Models\Compliance;
use App\Support\ComplianceRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResolvedComplianceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $records = $this->batch_id === null ? collect([$this->resource]) : Compliance::query()
            ->where('batch_id', $this->batch_id)
            ->when($this->compliance_group_id, fn ($query) => $query->where('compliance_group_id', $this->compliance_group_id), fn ($query) => $query->whereKey($this->id))
            ->with('guaranteeLetter')
            ->orderBy('guarantee_letter_id')
            ->get();

        return [
            'id' => $this->id,
            'type' => $this->complianceType?->name,
            'resubmitted_status' => ComplianceRoute::resubmissionStatus($this->complianceType?->name),
            'returned_at' => $this->returned_at,
            'resolved_at' => $this->resolved_at,
            'duration' => $this->duration,
            'remarks' => $this->remarks,
            'resolution_remarks' => $this->resolution_remarks,
            'is_voucher_compliance' => $this->batch_id !== null,
            'compliance_gls' => $records->map(fn (Compliance $record): array => [
                'gl_no' => $record->guaranteeLetter?->gl_no,
                'remarks' => $record->remarks,
            ])->all(),
        ];
    }
}
