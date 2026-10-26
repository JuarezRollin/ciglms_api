<?php

namespace App\Http\Resources;

use App\Models\GlTransmittal;
use App\Models\Status;
use App\Services\VoucherProcessingTimelineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuaranteeLetterResource extends JsonResource
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
            'batches' => BatchResource::collection($this->whenLoaded('batches')),
            'transmittals' => $this->whenLoaded('transmittals', fn (): array => $this->transmittals
                ->map(fn (GlTransmittal $transmittal): array => [
                    'id' => $transmittal->id,
                    'bundle_no' => $transmittal->bundle_no,
                    'creator' => [
                        'id' => $transmittal->creator->id,
                        'name' => $transmittal->creator->name,
                        'initials' => $transmittal->creator->initials,
                    ],
                ])->all()),
            'office_id' => $this->office_id,
            'gl_no' => $this->gl_no,
            'gl_date' => $this->gl_date,
            'client_id' => $this->client_id,
            'beneficiary_id' => $this->beneficiary_id,
            'address' => $this->address,
            'relationship_id' => $this->relationship_id,
            'service_provider_id' => $this->service_provider_id,
            'branch_id' => $this->branch_id,
            'fund_source_id' => $this->fund_source_id,
            'assistance_type_id' => $this->assistance_type_id,
            'status_id' => $this->status_id,
            'creation_action' => $request->user('api')?->hasRole('admin')
                ? match ((int) $this->status_id) {
                    Status::FOR_REVIEW_BY_CRD_ID => 'approve_gl',
                    Status::APPROVED_ID => 'release_gl',
                    default => null,
                }
                : null,
            'signatory_id' => $this->signatory_id,
            'swo_id' => $this->swo_id,
            'referring_party_id' => $this->referring_party_id,
            'creation_remarks' => $this->creation_remarks,
            'created_by' => $this->created_by,
            'reviewed_by_creation' => $this->reviewed_by_creation,
            'date_reviewed_by_creation' => $this->date_reviewed_by_creation,
            'approved_by' => $this->approved_by,
            'date_approved' => $this->date_approved,
            'released_by' => $this->released_by,
            'date_released' => $this->date_released,
            'endorsed_by' => $this->endorsed_by,
            'date_endorsed' => $this->date_endorsed,
            'onhold_by' => $this->onhold_by,
            'date_onhold' => $this->date_onhold,
            'cancelled_by_creation' => $this->cancelled_by_creation,
            'date_cancelled_by_creation' => $this->date_cancelled_by_creation,
            'gl_amount' => $this->gl_amount,
            'obligation_amount' => $this->obligation_amount,
            'supporting_documents' => GuaranteeLetterDocumentResource::collection($this->whenLoaded('supportingDocuments')),
            'can_update_obligation_amount' => $request->user('api')?->hasAnyRole(['admin', 'encoder', 'reviewer']) ?? false,
            'gl_validity' => $this->gl_validity,
            'received_by' => $this->received_by,
            'soa_received_by' => $this->soa_received_by,
            'pulled_out_by' => $this->pulled_out_by,
            'reviewed_by_rmcd' => $this->reviewed_by_rmcd,
            'cancelled_by_rmcd' => $this->cancelled_by_rmcd,
            'date_cancelled_by_rmcd' => $this->date_cancelled_by_rmcd,
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'norsa_no' => $this->norsa_no,
            'processing_remarks' => $this->processing_remarks,
            'date_received_gl' => $this->date_received_gl,
            'date_received_soa' => $this->date_received_soa,
            'date_pulled_out' => $this->date_pulled_out,
            'date_fwd_to_reviewer' => $this->date_fwd_to_reviewer,
            'date_fwd_to_dc' => $this->date_fwd_to_dc,
            'date_fwd_to_dir_ors' => $this->date_fwd_to_dir_ors,
            'date_fwd_to_budget' => $this->date_fwd_to_budget,
            'date_fwd_to_actg' => $this->date_fwd_to_actg,
            'date_fwd_to_dir_dv' => $this->date_fwd_to_dir_dv,
            'date_fwd_to_dir_fs' => $this->date_fwd_to_dir_fs,
            'date_fwd_to_cash' => $this->date_fwd_to_cash,
            'client' => ClientResource::make($this->whenLoaded('client')),
            'beneficiary' => BeneficiaryResource::make($this->whenLoaded('beneficiary')),
            'relationship' => RelationshipResource::make($this->whenLoaded('relationship')),
            'branch' => BranchResource::make($this->whenLoaded('branch')),
            'service_provider' => $this->whenLoaded('serviceProvider', fn (): array => [
                'id' => $this->serviceProvider->id,
                'name' => $this->serviceProvider->name,
            ]),
            'fund_source' => FundSourceResource::make($this->whenLoaded('fundSource')),
            'assistance_type' => AssistanceTypeResource::make($this->whenLoaded('assistanceType')),
            'status' => StatusResource::make($this->whenLoaded('status')),
            'latest_resolved_compliance' => ResolvedComplianceResource::make($this->whenLoaded('latestResolvedCompliance')),
            'processing_timeline' => $this->when(
                (bool) $this->resource->getAttribute('include_processing_timeline'),
                fn (): array => app(VoucherProcessingTimelineService::class)->forGuaranteeLetter($this->resource),
            ),
            'signatory' => SignatoryResource::make($this->whenLoaded('signatory')),
            'swo' => SwoResource::make($this->whenLoaded('swo')),
            'signatory_routes' => SignatoryRouteResource::collection($this->whenLoaded('signatoryRoutes')),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'initials' => $this->creator->initials,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
