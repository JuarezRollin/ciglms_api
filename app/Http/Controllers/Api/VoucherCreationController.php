<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVoucherBatchRequest;
use App\Http\Requests\ForwardVoucherBatchToBoxARequest;
use App\Http\Requests\ForwardVoucherBatchToBoxBRequest;
use App\Http\Requests\ForwardVoucherBatchToBoxCRequest;
use App\Http\Requests\ForwardVoucherBatchToBoxDRequest;
use App\Http\Requests\ForwardVoucherBatchToCashRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\MarkVoucherBatchPaidRequest;
use App\Http\Requests\RecordVoucherFinancialDetailsRequest;
use App\Http\Requests\ReturnBoxAReviewerForComplianceRequest;
use App\Http\Requests\ReturnFromAccountingForComplianceRequest;
use App\Http\Requests\ReturnFromBudgetForComplianceRequest;
use App\Http\Requests\ReturnVoucherBatchForComplianceRequest;
use App\Http\Requests\TagCipDirectorAndForwardToCashRequest;
use App\Http\Resources\VoucherBatchResource;
use App\Models\Batch;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\VoucherActionService;
use App\Services\VoucherCreationService;
use App\Services\VoucherPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class VoucherCreationController extends Controller
{
    /** @var list<string> */
    private const VOUCHER_STATUSES = [
        'For Review by RMCD',
        'For Compliance',
        'On Process',
        'For Obligation',
        'For Pre-Audit',
        'For Crediting',
        'Paid',
    ];

    public function index(ListRequest $request): JsonResponse
    {
        $user = $this->authorizeVoucherViewer($request);

        $batches = $this->paginate(
            Batch::query()
                ->with(VoucherCreationService::LIST_RELATIONSHIPS)
                ->where('created_by', $user->getKey()),
            $request,
            [],
            ['id', 'total_amount', 'created_at'],
            [],
            'created_at',
        );

        return VoucherBatchResource::collection($batches)->response();
    }

    public function allIndex(ListRequest $request): JsonResponse
    {
        $user = $this->authorizeVoucherViewer($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::VOUCHER_STATUSES)],
            'provider' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_source' => ['nullable', 'integer', 'exists:fund_sources,id'],
        ]);

        $query = Batch::query()
            ->when(
                ! $user->hasAnyRole(['admin', 'reviewer']),
                fn (Builder $query): Builder => $query->where('created_by', $user->getKey()),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->atStatus($status),
            )
            ->with(VoucherCreationService::LIST_RELATIONSHIPS);

        $batches = $this->paginate(
            $this->applyVoucherListFilters($query, $filters, $request->validated('search')),
            $request,
            [],
            ['id', 'total_amount', 'created_at'],
            [],
            'created_at',
        );

        return VoucherBatchResource::collection($batches)->response();
    }

    public function store(CreateVoucherBatchRequest $request, VoucherCreationService $service): JsonResponse
    {
        $batch = $service->create($request->validated(), $request->user('api'));

        return (new VoucherBatchResource($batch))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function reviewerOptions(Request $request): JsonResponse
    {
        abort_unless($request->user('api')?->hasAnyRole(['admin', 'voucher creation']), Response::HTTP_FORBIDDEN);

        return response()->json(['data' => User::query()
            ->role('reviewer')
            ->orderBy('name')
            ->get(['id', 'name', 'position'])]);
    }

    public function show(Request $request, Batch $batch): JsonResponse
    {
        $user = $this->authorizeVoucherViewer($request);
        abort_unless(
            (int) $batch->created_by === (int) $user->getKey()
            || $user->hasAnyRole(['admin', 'reviewer']),
            Response::HTTP_FORBIDDEN,
        );

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))
            ->response();
    }

    public function auditTrail(Request $request, Batch $batch, AuditTrailService $service): JsonResponse
    {
        $this->authorizeVoucherViewer($request);
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $activities = $service->forBatch($batch, $validated['per_page'] ?? 10);

        return response()->json($service->payload($activities));
    }

    public function reviewIndex(ListRequest $request): JsonResponse
    {
        $this->authorizeReviewer($request);

        return $this->voucherQueue($request, 'For Review by RMCD');
    }

    public function printReviewPdf(Request $request, Batch $batch, VoucherPdfService $pdfService): Response
    {
        $this->authorizePrintableBatch($request, $batch);

        $pdf = $pdfService->render($batch);

        return response($pdf, Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="voucher-'.$batch->getKey().'.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function printDv(Request $request, Batch $batch, VoucherPdfService $pdfService): Response
    {
        $this->authorizePrintableBatch($request, $batch);

        $pdf = $pdfService->renderDv($batch, now('Asia/Manila')->format('m/d/Y'));

        return response($pdf, Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="dv-'.$batch->getKey().'.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function printCombined(Request $request, Batch $batch, VoucherPdfService $pdfService): Response
    {
        $this->authorizePrintableBatch($request, $batch);

        $pdf = $pdfService->renderCombined($batch, now('Asia/Manila')->format('m/d/Y'));

        return response($pdf, Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="ors-dv-'.$batch->getKey().'.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function printTransmittal(Request $request, Batch $batch, VoucherPdfService $pdfService): Response
    {
        $this->authorizePrintableBatch($request, $batch);

        $pdf = $pdfService->renderTransmittal($batch);

        return response($pdf, Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="voucher-transmittal-'.$batch->getKey().'.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizePrintableBatch(Request $request, Batch $batch): void
    {
        $user = $this->authorizeVoucherViewer($request);
        abort_unless(
            (int) $batch->created_by === (int) $user->getKey()
            || $user->hasAnyRole(['admin', 'reviewer']),
            Response::HTTP_FORBIDDEN,
        );
        abort_unless(Batch::query()->atStatus('For Review by RMCD')->whereKey($batch->getKey())->exists(), Response::HTTP_NOT_FOUND);
    }

    public function onProcessIndex(ListRequest $request): JsonResponse
    {
        $reviewedByBoxA = $this->onProcessReviewedByBoxAFilter($request);

        $currentHandler = $request->validate([
            'current_handler' => ['nullable', 'in:box_a_reviewer,outgoing'],
        ])['current_handler'] ?? null;

        if ($reviewedByBoxA === null) {
            $reviewedByBoxA = match ($currentHandler) {
                'box_a_reviewer' => false,
                'outgoing' => true,
                default => null,
            };
        }

        return $this->voucherQueue($request, 'On Process', $reviewedByBoxA, null, null, [
            'can_filter_current_handler' => $request->user('api')?->hasAnyRole(['admin', 'reviewer']) ?? false,
        ]);
    }

    public function onProcessShow(Request $request, Batch $batch): JsonResponse
    {
        $reviewedByBoxA = $this->onProcessReviewedByBoxAFilter($request);
        $query = Batch::query()->atStatus('On Process')->whereKey($batch->getKey());
        if ($reviewedByBoxA === true) {
            $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNull('box_a_reviewed_by'));
        } elseif ($reviewedByBoxA === false) {
            $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNotNull('box_a_reviewed_by'));
        }
        abort_unless($query->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))
            ->response();
    }

    public function boxAReviewIndex(ListRequest $request): JsonResponse
    {
        $this->authorizeBoxAReviewer($request);

        return $this->voucherQueue($request, 'On Process', false);
    }

    public function boxAReviewShow(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeBoxAReviewer($request);
        abort_unless(Batch::query()->atStatus('On Process')->whereDoesntHave('gls', fn (Builder $query): Builder => $query->whereNotNull('box_a_reviewed_by'))->whereKey($batch->getKey())->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))->response();
    }

    public function forObligationIndex(ListRequest $request): JsonResponse
    {
        $this->authorizeForObligationViewer($request);

        return $this->voucherQueue($request, 'For Obligation');
    }

    public function forObligationShow(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeForObligationViewer($request);
        abort_unless(Batch::query()->atStatus('For Obligation')->whereKey($batch->getKey())->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))
            ->response();
    }

    public function forPreAuditIndex(ListRequest $request): JsonResponse
    {
        $this->authorizeForPreAuditViewer($request);

        return $this->voucherQueue($request, 'For Pre-Audit');
    }

    public function forPreAuditShow(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeForPreAuditViewer($request);
        abort_unless(Batch::query()->atStatus('For Pre-Audit')->whereKey($batch->getKey())->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))
            ->response();
    }

    public function forCreditingIndex(ListRequest $request): JsonResponse
    {
        $handler = $this->forCreditingHandlerFilter($request);

        return $this->voucherQueue($request, 'For Crediting', additional: ['can_filter_current_handler' => $handler === null], stageFilter: fn (Builder $query): Builder => $this->applyForCreditingHandlerFilter($query, $handler));
    }

    public function forCreditingShow(Request $request, Batch $batch): JsonResponse
    {
        $handler = $this->forCreditingHandlerFilter($request);
        abort_unless($this->applyForCreditingHandlerFilter(Batch::query()->atStatus('For Crediting')->whereKey($batch->getKey()), $handler)->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))
            ->response();
    }

    public function paidIndex(ListRequest $request): JsonResponse
    {
        $this->authorizeOutgoingViewer($request);

        return $this->voucherQueue($request, 'Paid');
    }

    public function paidShow(Request $request, Batch $batch): JsonResponse
    {
        $this->authorizeOutgoingViewer($request);
        abort_unless(Batch::query()->atStatus('Paid')->whereKey($batch->getKey())->exists(), Response::HTTP_NOT_FOUND);

        return (new VoucherBatchResource($batch->load(VoucherCreationService::DETAIL_RELATIONSHIPS)))->response();
    }

    private function voucherQueue(
        ListRequest $request,
        string $statusName,
        ?bool $reviewedByBoxA = null,
        ?string $handoffColumn = null,
        ?bool $handoffComplete = null,
        array $additional = [],
        ?\Closure $stageFilter = null,
    ): JsonResponse {
        $filters = $request->validate([
            'provider' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_source' => ['nullable', 'integer', 'exists:fund_sources,id'],
        ]);
        $query = Batch::query()->atStatus($statusName)
            ->when($reviewedByBoxA === true, fn (Builder $query) => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNull('box_a_reviewed_by')))
            ->when($reviewedByBoxA === false, fn (Builder $query) => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNotNull('box_a_reviewed_by')))
            ->when($handoffComplete === true, fn (Builder $query) => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNull($handoffColumn)))
            ->when($handoffComplete === false, fn (Builder $query) => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNotNull($handoffColumn)))
            ->when($stageFilter !== null, fn (Builder $query): Builder => $stageFilter($query))
            ->with(VoucherCreationService::LIST_RELATIONSHIPS);

        $batches = $this->paginate(
            $this->applyVoucherListFilters($query, $filters, $request->validated('search')),
            $request,
            [],
            ['id', 'total_amount', 'created_at'],
            [],
            'created_at',
        );

        return VoucherBatchResource::collection($batches)
            ->additional($additional)
            ->response();
    }

    /** @param array{provider?: int|null, fund_source?: int|null} $filters */
    private function applyVoucherListFilters(Builder $query, array $filters, ?string $search): Builder
    {
        return $query
            ->when($filters['provider'] ?? null, function (Builder $query, int $branchId): void {
                $query->whereHas('gls', fn (Builder $glQuery): Builder => $glQuery->where('branch_id', $branchId));
            })
            ->when($filters['fund_source'] ?? null, function (Builder $query, int $fundSourceId): void {
                $query->whereHas('gls', fn (Builder $glQuery): Builder => $glQuery->where('fund_source_id', $fundSourceId));
            })
            ->when($search, function (Builder $query, string $search): void {
                $query->whereHas('voucher', fn (Builder $voucherQuery): Builder => $voucherQuery->where('voucher_no', 'like', "%{$search}%"));
            });
    }

    public function forwardToBoxA(
        ForwardVoucherBatchToBoxARequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->forwardToBoxA($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function approveBoxAReview(Request $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        $this->authorizeBoxAReviewer($request);

        return (new VoucherBatchResource($service->approveBoxAReview($batch, $request->user('api'))))->response();
    }

    public function forwardToBoxB(
        ForwardVoucherBatchToBoxBRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->forwardToBoxB($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function returnFromBoxAForCompliance(
        ReturnBoxAReviewerForComplianceRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->returnFromBoxAForCompliance($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function forwardToBoxC(
        ForwardVoucherBatchToBoxCRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->forwardToBoxC($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function returnFromBudgetForCompliance(
        ReturnFromBudgetForComplianceRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->returnFromBudgetForCompliance($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function forwardToBoxD(
        ForwardVoucherBatchToBoxDRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        return (new VoucherBatchResource($service->forwardToBoxD($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function returnFromAccountingForCompliance(
        ReturnFromAccountingForComplianceRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->returnFromAccountingForCompliance($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    public function tagSignedByCipDirector(TagCipDirectorAndForwardToCashRequest $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        $this->authorizeBoxAReviewer($request);

        return (new VoucherBatchResource($service->tagSignedByCipDirector($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function forwardToCash(ForwardVoucherBatchToCashRequest $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        return (new VoucherBatchResource($service->forwardToCash($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function markAsPaid(MarkVoucherBatchPaidRequest $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        return (new VoucherBatchResource($service->markAsPaid($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function recordFinancialDetails(RecordVoucherFinancialDetailsRequest $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        return (new VoucherBatchResource($service->recordFinancialDetails($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function returnFromCashForCompliance(ReturnVoucherBatchForComplianceRequest $request, Batch $batch, VoucherActionService $service): JsonResponse
    {
        return (new VoucherBatchResource($service->returnFromCashForCompliance($batch, $request->validated(), $request->user('api'))))->response();
    }

    public function returnForCompliance(
        ReturnVoucherBatchForComplianceRequest $request,
        Batch $batch,
        VoucherActionService $service,
    ): JsonResponse {
        $updatedBatch = $service->returnForCompliance($batch, $request->validated(), $request->user('api'));

        return (new VoucherBatchResource($updatedBatch))->response();
    }

    private function authorizeReviewer(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function authorizeVoucherViewer(Request $request): User
    {
        $user = $request->user('api');

        abort_unless(
            $user !== null && $user->hasAnyRole(['admin', 'voucher creation', 'reviewer']),
            Response::HTTP_FORBIDDEN,
        );

        return $user;
    }

    private function authorizeOutgoingViewer(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function authorizeForObligationViewer(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer', 'outgoing', 'compliance']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function authorizeForPreAuditViewer(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer', 'outgoing', 'compliance']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function onProcessReviewedByBoxAFilter(Request $request): ?bool
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer', 'outgoing', 'box a reviewer']), Response::HTTP_FORBIDDEN);

        return match (true) {
            $user->hasRole('box a reviewer') => false,
            $user->hasRole('outgoing') => true,
            $user->hasRole('reviewer') => true,
            default => null,
        };
    }

    private function authorizeBoxAReviewer(Request $request): User
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'box a reviewer']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function forCreditingHandlerFilter(Request $request): ?string
    {
        $user = $request->user('api');
        abort_unless($user !== null && $user->hasAnyRole(['admin', 'reviewer', 'outgoing', 'box a reviewer']), Response::HTTP_FORBIDDEN);
        if ($user->hasRole('box a reviewer')) {
            return 'box_a_reviewer';
        }
        if ($user->hasRole('outgoing')) {
            return 'outgoing';
        }
        if ($user->hasRole('reviewer')) {
            return null;
        }
        $handler = $request->query('current_handler');
        abort_unless(in_array($handler, [null, '', 'box_a_reviewer', 'outgoing', 'reviewer'], true), Response::HTTP_UNPROCESSABLE_ENTITY);

        return filled($handler) ? $handler : null;
    }

    private function applyForCreditingHandlerFilter(Builder $query, ?string $handler): Builder
    {
        return match ($handler) {
            'box_a_reviewer' => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNotNull('date_fwd_to_dir_fs')),
            'outgoing' => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNull('date_fwd_to_dir_fs'))->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNotNull('date_fwd_to_cash')),
            'reviewer' => $query->whereDoesntHave('gls', fn (Builder $letters): Builder => $letters->whereNull('date_fwd_to_cash')),
            default => $query,
        };
    }
}
