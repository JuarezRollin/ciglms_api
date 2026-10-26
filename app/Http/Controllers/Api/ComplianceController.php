<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\ResolveComplianceRequest;
use App\Http\Resources\ComplianceResource;
use App\Models\Compliance;
use App\Models\ComplianceType;
use App\Services\ComplianceAccess;
use App\Services\VoucherActionService;
use App\Services\VoucherCreationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ComplianceController extends Controller
{
    public function access(Request $request, ComplianceAccess $access): JsonResponse
    {
        $user = $request->user('api');
        $types = ComplianceType::query()
            ->when(! $user->hasRole('admin'), fn (Builder $query): Builder => $query->whereIn('name', $access->typeNamesFor($user)))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (ComplianceType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'is_active' => $type->is_active,
                'kinds' => $access->kindsForType($type->name),
            ]);
        $counts = $access->openCountsFor($user);

        return response()->json([
            'tabs' => $access->tabsFor($user),
            'types' => $types,
            'counts' => $counts,
            'total_open' => $counts['gl'] + $counts['voucher'],
        ]);
    }

    public function index(ListRequest $request, ComplianceAccess $access): JsonResponse
    {
        $filters = $request->validate([
            'kind' => ['required', 'in:gl,voucher'],
            'compliance_type_id' => ['nullable', 'integer', 'exists:compliance_types,id'],
        ]);
        $user = $request->user('api');
        abort_unless(in_array($filters['kind'], $access->tabsFor($user), true), Response::HTTP_FORBIDDEN);

        $query = $access->scopeVisible(
            Compliance::query()->open()->with($this->listRelationships()),
            $user,
        );
        if ($filters['kind'] === 'gl') {
            $query->whereNotNull('guarantee_letter_id')->whereNull('batch_id');
        } else {
            $query->whereNotNull('batch_id')->whereIn('id', Compliance::query()
                ->open()
                ->whereNotNull('batch_id')
                ->selectRaw('MIN(id)')
                ->groupBy('batch_id', 'compliance_type_id'));
        }

        if (isset($filters['compliance_type_id'])) {
            $query->where('compliance_type_id', $filters['compliance_type_id']);
        }

        if (($search = $request->validated('search')) !== null) {
            $query->where(function (Builder $searchQuery) use ($filters, $search): void {
                if ($filters['kind'] === 'gl') {
                    $searchQuery->whereHas('guaranteeLetter', fn (Builder $letterQuery): Builder => $letterQuery->where('gl_no', 'like', "%{$search}%"));
                } else {
                    $searchQuery->whereHas('batch.voucher', fn (Builder $voucherQuery): Builder => $voucherQuery->where('voucher_no', 'like', "%{$search}%"));
                }
            });
        }

        $records = $this->paginate($query, $request, [], ['returned_at', 'id'], [], 'returned_at');

        return ComplianceResource::collection($records)->response();
    }

    public function show(Request $request, Compliance $compliance, ComplianceAccess $access): JsonResponse
    {
        abort_if($compliance->resolved_at !== null, Response::HTTP_NOT_FOUND);
        $user = $request->user('api');
        $kind = $compliance->batch_id !== null ? 'voucher' : 'gl';
        abort_unless(in_array($kind, $access->tabsFor($user), true), Response::HTTP_FORBIDDEN);
        $compliance->load('complianceType');
        abort_unless($access->canAccessType($user, $compliance->complianceType->name), Response::HTTP_FORBIDDEN);

        $compliance->load($this->detailRelationships());

        if ($compliance->batch_id === null) {
            $compliance->guaranteeLetter?->setAttribute('include_processing_timeline', true);
        } else {
            $compliance->setRelation('voucherComplianceRecords', Compliance::query()
                ->open()
                ->where('batch_id', $compliance->batch_id)
                ->where('compliance_type_id', $compliance->compliance_type_id)
                ->with([
                    'guaranteeLetter.client',
                    'guaranteeLetter.branch.serviceProvider',
                ])
                ->orderBy('guarantee_letter_id')
                ->get());
        }

        return (new ComplianceResource($compliance))->response();
    }

    public function resolve(
        ResolveComplianceRequest $request,
        Compliance $compliance,
        VoucherActionService $service,
    ): Response {
        $service->resolveForCompliance($compliance, $request->validated(), $request->user('api'));

        return response()->noContent();
    }

    /** @return list<string> */
    private function listRelationships(): array
    {
        return [
            'complianceType',
            'guaranteeLetter.client',
            'guaranteeLetter.branch.serviceProvider',
            'guaranteeLetter.status',
            'batch.voucher',
            'batch.gls.client',
            'batch.gls.branch.serviceProvider',
            'batch.gls.fundSource',
            'batch.gls.status',
        ];
    }

    /** @return list<string> */
    private function detailRelationships(): array
    {
        return [
            ...$this->listRelationships(),
            'guaranteeLetter.beneficiary',
            'guaranteeLetter.relationship',
            'guaranteeLetter.assistanceType',
            'guaranteeLetter.category',
            'guaranteeLetter.fundSource',
            'guaranteeLetter.batches.voucher',
            'guaranteeLetter.batches.ors',
            'guaranteeLetter.batches.dv',
            'guaranteeLetter.batches.cheque',
            ...array_map(
                fn (string $relationship): string => 'batch.'.$relationship,
                VoucherCreationService::DETAIL_RELATIONSHIPS,
            ),
        ];
    }
}
