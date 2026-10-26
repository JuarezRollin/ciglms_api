<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuaranteeLetterActionRequest;
use App\Http\Requests\GuaranteeLetterBulkApproveRequest;
use App\Http\Requests\GuaranteeLetterBulkReleaseRequest;
use App\Http\Requests\GuaranteeLetterListRequest;
use App\Http\Requests\GuaranteeLetterRequest;
use App\Http\Requests\GuaranteeLetterReviewListRequest;
use App\Http\Resources\GuaranteeLetterResource;
use App\Models\Category;
use App\Models\GuaranteeLetter;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\Swo;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\GuaranteeLetterActionService;
use App\Services\GuaranteeLetterSigningService;
use App\Services\SignatoryRoutePlanner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuaranteeLetterController extends Controller
{
    /** @var list<string> */
    private const RELATIONSHIPS = [
        'batches.voucher',
        'batches.ors',
        'batches.dv',
        'batches.cheque',
        'batches.latestResolvedCompliance.complianceType',
        'client',
        'beneficiary',
        'relationship',
        'branch.serviceProvider',
        'fundSource',
        'assistanceType',
        'status',
        'category',
        'signatory',
        'creator',
        'latestResolvedCompliance.complianceType',
    ];

    public function index(GuaranteeLetterListRequest $request): JsonResponse
    {
        $query = GuaranteeLetter::query()->with(self::RELATIONSHIPS);
        if ($request->validated('status_scope') === 'cancelled') {
            $query->whereIn('status_id', [Status::CANCELLED_BY_CRD_ID, Status::CANCELLED_BY_RMCD_ID]);
        }

        if (($createdFrom = $request->validated('created_from')) !== null) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if (($createdTo = $request->validated('created_to')) !== null) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        if (($search = $request->validated('search')) !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('gl_no', 'like', "%{$search}%")
                    ->orWhere('norsa_no', 'like', "%{$search}%");
                foreach (['voucher', 'ors', 'dv', 'cheque'] as $reference) {
                    $query->orWhereHas('batches.'.$reference, function (Builder $query) use ($reference, $search): void {
                        $query->where($reference.'_no', 'like', "%{$search}%");
                    });
                }
            });
        }
        $guaranteeLetters = $this->paginate(
            $query,
            $request,
            [],
            ['gl_no', 'gl_date', 'gl_amount', 'created_at'],
            [
                'service_provider_id',
                'created_by',
                'swo_id',
                'client_id',
                'beneficiary_id',
                'relationship_id',
                'branch_id',
                'fund_source_id',
                'assistance_type_id',
                'status_id',
                'category_id',
                'signatory_id',
            ],
            'gl_date',
        );

        return GuaranteeLetterResource::collection($guaranteeLetters)->response();
    }

    public function listFilterOptions(GuaranteeLetterListRequest $request): JsonResponse
    {
        $letters = GuaranteeLetter::query();

        if ($request->validated('status_scope') === 'cancelled') {
            $letters->whereIn('status_id', [Status::CANCELLED_BY_CRD_ID, Status::CANCELLED_BY_RMCD_ID]);
        } elseif (($statusId = $request->validated('filter.status_id')) !== null) {
            $letters->where('status_id', $statusId);
        }

        return $this->creationStageFilterOptions($letters);
    }

    private function generateGlNumber()
    {
        $getTotalGL = GuaranteeLetter::count();
        $getYear = now()->format('Y');
        $nextGLNumber = str_pad($getTotalGL + 1, 5, '0', STR_PAD_LEFT);

        return 'GL-'.$getYear.'-'.$nextGLNumber;
    }

    public function store(GuaranteeLetterRequest $request, SignatoryRoutePlanner $routePlanner): JsonResponse
    {
        $user = auth()->user();
        if ($user?->office_id === null) {
            throw ValidationException::withMessages(['office_id' => 'Assign an office to your account before creating a guarantee letter.']);
        }

        $guaranteeLetter = DB::transaction(function () use ($request, $routePlanner, $user): GuaranteeLetter {
            $letter = GuaranteeLetter::query()->create([
                'gl_no' => $this->generateGlNumber() ?? 0,
                'office_id' => $user->office_id,
                'created_by' => $user->id,
                ...$request->validated(),
                'status_id' => Status::FOR_REVIEW_BY_CRD_ID,
            ]);
            $routePlanner->createFor($letter);

            return $letter;
        });

        return (new GuaranteeLetterResource($guaranteeLetter->load([...self::RELATIONSHIPS, 'signatoryRoutes.signatory'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(GuaranteeLetter $guaranteeLetter): JsonResponse
    {
        $guaranteeLetter->setAttribute('include_processing_timeline', true);

        return (new GuaranteeLetterResource($guaranteeLetter->load([...self::RELATIONSHIPS, 'signatoryRoutes.signatory', 'supportingDocuments.uploader'])))->response();
    }

    public function auditTrail(Request $request, GuaranteeLetter $guaranteeLetter, AuditTrailService $service): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $activities = $service->forGuaranteeLetter($guaranteeLetter, $validated['per_page'] ?? 10);

        return response()->json($service->payload($activities));
    }

    public function update(GuaranteeLetterRequest $request, GuaranteeLetter $guaranteeLetter): JsonResponse
    {
        $guaranteeLetter->update($request->validated());

        return (new GuaranteeLetterResource(
            $guaranteeLetter->refresh()->load([...self::RELATIONSHIPS, 'signatoryRoutes.signatory']),
        ))->response();
    }

    public function processAction(GuaranteeLetterActionRequest $request, GuaranteeLetter $guaranteeLetter, GuaranteeLetterActionService $service): JsonResponse
    {
        $letter = $service->processAction($guaranteeLetter, $request->validated(), $request->user('api'));
        $letter->setAttribute('include_processing_timeline', true);

        return (new GuaranteeLetterResource($letter->load([...self::RELATIONSHIPS, 'supportingDocuments.uploader'])))->response();
    }

    public function approveSelected(GuaranteeLetterBulkApproveRequest $request, GuaranteeLetterActionService $service): JsonResponse
    {
        $letterIds = $request->validated('gl_ids');
        sort($letterIds, SORT_NUMERIC);

        DB::transaction(function () use ($letterIds, $request, $service): void {
            foreach ($letterIds as $letterId) {
                $letter = GuaranteeLetter::query()->findOrFail($letterId);
                $service->processAction($letter, ['action' => 'approve_gl'], $request->user('api'));
            }
        });

        return response()->json(['approved_count' => count($letterIds)]);
    }

    public function releaseSelected(GuaranteeLetterBulkReleaseRequest $request, GuaranteeLetterActionService $service): JsonResponse
    {
        $letterIds = $request->validated('gl_ids');
        sort($letterIds, SORT_NUMERIC);

        DB::transaction(function () use ($letterIds, $request, $service): void {
            foreach ($letterIds as $letterId) {
                $letter = GuaranteeLetter::query()->findOrFail($letterId);
                $service->processAction($letter, ['action' => 'release_gl'], $request->user('api'));
            }
        });

        return response()->json(['released_count' => count($letterIds)]);
    }

    public function printGL(int $id, GuaranteeLetterSigningService $signingService): JsonResponse
    {
        $gl = GuaranteeLetter::query()->findOrFail($id);
        $pdfContent = $signingService->getOrSign($gl, function (GuaranteeLetter $gl): string {
            $gl->load(['client', 'beneficiary', 'category', 'serviceProvider', 'signatory', 'creator']);
            $glDate = $this->convertDate($gl->gl_date, 'F d, Y');
            $glValidity = $this->convertDate($gl->gl_validity, 'F d, Y');
            $glAmountName = $this->convertAmount($gl->gl_amount);
            $glCreatedAt = $this->convertDate($gl->created_at, 'n/j/Y g:i:s A');

            return Pdf::loadView('printing.guarantee_letter', compact('gl', 'glDate', 'glValidity', 'glAmountName', 'glCreatedAt'))
                ->setPaper('a4')
                ->output();
        });
        $gl->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $gl->signed_pdf_filename ?? "guarantee-letter-no-{$gl->gl_no}-{$gl->gl_date->toDateString()}.pdf",
                'pdf' => base64_encode($pdfContent),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function convertDate($date = '', $format = 'Y-m-d')
    {
        $dateValue = Carbon::parse($date);

        return $dateValue->format($format);
    }

    private function convertAmount(int $amount)
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
        $words = $formatter->format($amount);

        return ucwords($words);
    }

    private function creationStageLetters(int $statusId): Builder
    {
        return GuaranteeLetter::query()
            ->where('status_id', $statusId)
            ->whereDoesntHave('batches');
    }

    public function reviewFilterOptions(): JsonResponse
    {
        return $this->creationStageFilterOptions($this->creationStageLetters(Status::FOR_REVIEW_BY_CRD_ID));
    }

    public function approvalFilterOptions(): JsonResponse
    {
        return $this->creationStageFilterOptions($this->creationStageLetters(Status::APPROVED_ID));
    }

    public function releasedFilterOptions(): JsonResponse
    {
        return $this->creationStageFilterOptions($this->creationStageLetters(Status::RELEASED_ID));
    }

    public function endorsementFilterOptions(): JsonResponse
    {
        return $this->creationStageFilterOptions($this->creationStageLetters(Status::FOR_ENDORSEMENT_ID));
    }

    private function creationStageFilterOptions(Builder $letters): JsonResponse
    {
        return response()->json([
            'service_providers' => ServiceProvider::query()
                ->whereIn('id', (clone $letters)->select('service_provider_id'))
                ->orderBy('name')->get(['id', 'name']),
            'signatories' => Signatory::query()
                ->whereIn('id', (clone $letters)->select('signatory_id'))
                ->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()
                ->whereIn('id', (clone $letters)->select('category_id'))
                ->orderBy('name')->get(['id', 'name']),
            'creators' => User::query()
                ->whereIn('id', (clone $letters)->select('created_by'))
                ->orderBy('name')->get(['id', 'name']),
            'swos' => Swo::query()
                ->whereIn('id', (clone $letters)->select('swo_id'))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function getForReviewGL(GuaranteeLetterReviewListRequest $request): JsonResponse
    {
        return $this->creationStageList($this->creationStageLetters(Status::FOR_REVIEW_BY_CRD_ID), $request);
    }

    public function getForApprovalGL(GuaranteeLetterReviewListRequest $request): JsonResponse
    {
        return $this->creationStageList($this->creationStageLetters(Status::APPROVED_ID), $request);
    }

    private function creationStageList(Builder $query, GuaranteeLetterReviewListRequest $request): JsonResponse
    {
        $query->with([...self::RELATIONSHIPS, 'swo']);
        if (($createdFrom = $request->validated('created_from')) !== null) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if (($createdTo = $request->validated('created_to')) !== null) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        $guaranteeLetters = $this->paginate(
            $query,
            $request,
            ['gl_no'],
            ['gl_no', 'gl_date', 'gl_amount', 'created_at'],
            [
                'service_provider_id',
                'created_by',
                'swo_id',
                'client_id',
                'beneficiary_id',
                'relationship_id',
                'branch_id',
                'fund_source_id',
                'assistance_type_id',
                'status_id',
                'category_id',
                'signatory_id',
            ],
            'gl_date',
        );

        return GuaranteeLetterResource::collection($guaranteeLetters)->response();
    }

    public function getForReleaseGL(GuaranteeLetterReviewListRequest $request): JsonResponse
    {
        return $this->creationStageList($this->creationStageLetters(Status::RELEASED_ID), $request);
    }

    public function getForEndorsementGL(GuaranteeLetterReviewListRequest $request): JsonResponse
    {
        $query = $this->creationStageLetters(Status::FOR_ENDORSEMENT_ID)
            ->with(['transmittals' => fn (BelongsToMany $transmittals): BelongsToMany => $transmittals
                ->whereNull('gl_transmittals.cancelled_at')
                ->with('creator')]);

        return $this->creationStageList($query, $request);
    }
}
