<?php

namespace App\Http\Resources;

use App\Services\VoucherProcessingTimelineService;
use Illuminate\Http\Request;

class VoucherBatchResource extends BatchResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'status' => $this->whenLoaded('gls', function () {
                // The voucher has no status column; all included GLs must agree.
                $status = $this->gls->first()?->status;

                return $status !== null
                    && $this->gls->every(fn ($letter): bool => $letter->status_id === $status->id)
                    ? new StatusResource($status)
                    : null;
            }),
            'on_process_owner' => $this->whenLoaded('gls', function () {
                $letters = $this->gls;

                if ($letters->isEmpty() || $letters->first()?->status?->name !== 'On Process') {
                    return null;
                }

                return $letters->every(fn ($letter): bool => $letter->box_a_reviewed_by !== null)
                    ? 'outgoing'
                    : 'box_a_reviewer';
            }),
            'for_crediting_owner' => $this->whenLoaded('gls', function () {
                $letters = $this->gls;
                if ($letters->isEmpty() || $letters->first()?->status?->name !== 'For Crediting') {
                    return null;
                }
                if ($letters->every(fn ($letter): bool => $letter->date_fwd_to_dir_fs === null)) {
                    return 'box_a_reviewer';
                }

                return $letters->every(fn ($letter): bool => $letter->date_fwd_to_cash === null)
                    ? 'outgoing'
                    : 'reviewer';
            }),
            'can_return_for_obligation_compliance' => $this->whenLoaded('gls', function () use ($request): bool {
                return $this->gls->first()?->status?->name === 'For Obligation'
                    && ($request->user('api')?->hasAnyRole(['admin', 'reviewer', 'compliance']) ?? false);
            }),
            'can_return_for_pre_audit_compliance' => $this->whenLoaded('gls', function () use ($request): bool {
                return $this->gls->first()?->status?->name === 'For Pre-Audit'
                    && ($request->user('api')?->hasAnyRole(['admin', 'reviewer', 'compliance']) ?? false);
            }),
            'requires_ors_number' => $request->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false,
            'requires_dv_number' => $request->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false,
            'can_record_financial_details' => $this->whenLoaded('gls', function () use ($request): bool {
                $status = $this->gls->first()?->status?->name;

                return in_array($status, ['For Pre-Audit', 'For Crediting', 'Paid'], true)
                    && ($request->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false);
            }),
            'can_take_workflow_action' => $this->whenLoaded('gls', function () use ($request): bool {
                $user = $request->user('api');
                $status = $this->gls->first()?->status?->name;
                if ($status === 'For Review by RMCD') {
                    return $user?->hasAnyRole(['admin', 'reviewer']) ?? false;
                }
                if ($user?->hasRole('admin')) {
                    return in_array($status, ['On Process', 'For Obligation', 'For Pre-Audit', 'For Crediting'], true);
                }
                if ($status === 'On Process' && $user?->hasRole('reviewer')) {
                    return true;
                }
                if ($status === 'On Process') {
                    $waitingForBoxA = $this->gls->every(fn ($letter): bool => $letter->box_a_reviewed_by === null);

                    return $waitingForBoxA ? $user?->hasRole('box a reviewer') ?? false : $user?->hasRole('outgoing') ?? false;
                }
                if (in_array($status, ['For Obligation', 'For Pre-Audit'], true)) {
                    return $user?->hasAnyRole(['reviewer', 'outgoing', 'compliance']) ?? false;
                }
                if ($status === 'For Crediting') {
                    if ($this->gls->every(fn ($letter): bool => $letter->date_fwd_to_dir_fs === null)) {
                        return $user?->hasRole('box a reviewer') ?? false;
                    }
                    if ($this->gls->every(fn ($letter): bool => $letter->date_fwd_to_cash === null)) {
                        return $user?->hasRole('outgoing') ?? false;
                    }

                    return $user?->hasRole('reviewer') ?? false;
                }

                return false;
            }),
            'processing_timeline' => $this->when(
                $this->resource->relationLoaded('latestResolvedCompliance'),
                fn (): array => app(VoucherProcessingTimelineService::class)->forBatch($this->resource),
            ),
        ];
    }
}
