<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListGlTransmittalRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\SaveGlTransmittalRequest;
use App\Http\Resources\GlTransmittalResource;
use App\Http\Resources\GuaranteeLetterResource;
use App\Models\Category;
use App\Models\GlTransmittal;
use App\Models\GuaranteeLetter;
use App\Models\ServiceProvider;
use App\Models\Signatory;
use App\Models\Status;
use App\Models\User;
use App\Services\GlTransmittalPdfService;
use App\Services\GlTransmittalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class GlTransmittalController extends Controller
{
    public function index(ListGlTransmittalRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = GlTransmittal::query()
            ->with(['category', 'creator', 'receiver'])
            ->withCount(['gls', 'gls as received_gl_count' => fn (Builder $query): Builder => $query->whereNotNull('guarantee_letters.date_received_gl')]);
        if (($filters['scope'] ?? null) === 'receiving') {
            $query->whereNull('cancelled_at');
        }

        foreach (['category_id', 'created_by', 'received_by'] as $column) {
            if (isset($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filters['date_from'], 'Asia/Manila')->startOfDay()->utc());
        }
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filters['date_to'], 'Asia/Manila')->endOfDay()->utc());
        }

        $receiptStatus = $filters['receipt_status'] ?? null;
        if ($receiptStatus === 'cancelled') {
            $query->whereNotNull('cancelled_at');
        } elseif ($receiptStatus !== null) {
            $query->whereNull('cancelled_at');
            if ($receiptStatus === 'pending' || $receiptStatus === 'partial') {
                $query->whereHas('gls', fn (Builder $gls): Builder => $gls->whereNull('guarantee_letters.date_received_gl'));
            }
            if ($receiptStatus === 'partial' || $receiptStatus === 'received') {
                $query->whereHas('gls', fn (Builder $gls): Builder => $gls->whereNotNull('guarantee_letters.date_received_gl'));
            }
            if ($receiptStatus === 'pending') {
                $query->whereDoesntHave('gls', fn (Builder $gls): Builder => $gls->whereNotNull('guarantee_letters.date_received_gl'));
            }
            if ($receiptStatus === 'received') {
                $query->whereDoesntHave('gls', fn (Builder $gls): Builder => $gls->whereNull('guarantee_letters.date_received_gl'));
            }
        }

        $transmittals = $this->paginate(
            $query,
            $request, ['bundle_no'], ['id', 'bundle_no', 'created_at'],
        );

        return GlTransmittalResource::collection($transmittals)
            ->additional(['permissions' => $this->permissions($request->user('api'))])
            ->response();
    }

    public function filterOptions(ListGlTransmittalRequest $request): JsonResponse
    {
        $transmittals = GlTransmittal::query();
        if ($request->validated('scope') === 'receiving') {
            $transmittals->whereNull('cancelled_at');
        }

        return response()->json([
            'categories' => Category::query()->whereIn('id', (clone $transmittals)->select('category_id'))->orderBy('name')->get(['id', 'name']),
            'creators' => User::query()->whereIn('id', (clone $transmittals)->select('created_by'))->orderBy('name')->get(['id', 'name', 'initials']),
            'receivers' => User::query()->whereIn('id', (clone $transmittals)->whereNotNull('received_by')->select('received_by'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function eligible(ListRequest $request): JsonResponse
    {
        abort_unless($this->permissions($request->user('api'))['can_manage'], Response::HTTP_FORBIDDEN);
        $query = $this->eligibleLetters()
            ->with(['client', 'beneficiary', 'serviceProvider', 'branch', 'signatory', 'creator', 'category', 'status']);

        if (($search = $request->validated('search')) !== null) {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('gl_no', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $clientQuery): Builder => $clientQuery->where('full_name', 'like', "%{$search}%"));
            });
        }

        return GuaranteeLetterResource::collection($this->paginate(
            $query, $request, [], ['id', 'gl_no', 'gl_date', 'created_at'],
            ['service_provider_id', 'created_by', 'signatory_id'], 'id',
        ))->response();
    }

    public function eligibleFilterOptions(Request $request): JsonResponse
    {
        abort_unless($this->permissions($request->user('api'))['can_manage'], Response::HTTP_FORBIDDEN);
        $eligible = $this->eligibleLetters();

        return response()->json([
            'service_providers' => ServiceProvider::query()
                ->whereIn('id', (clone $eligible)->select('service_provider_id'))
                ->orderBy('name')->get(['id', 'name']),
            'gl_creators' => User::query()
                ->whereIn('id', (clone $eligible)->select('created_by'))
                ->orderBy('name')->get(['id', 'name', 'initials']),
            'signatories' => Signatory::query()
                ->whereIn('id', (clone $eligible)->select('signatory_id'))
                ->orderBy('name')->get(['id', 'name', 'initials']),
        ]);
    }

    private function eligibleLetters(): Builder
    {
        return GuaranteeLetter::query()
            ->where('status_id', Status::RELEASED_ID)
            ->whereDoesntHave('transmittals', fn (Builder $query): Builder => $query->whereNull('gl_transmittals.cancelled_at'));
    }

    public function store(SaveGlTransmittalRequest $request, GlTransmittalService $service): JsonResponse
    {
        return (new GlTransmittalResource($service->create($request->validated(), $request->user('api'))))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(GlTransmittal $transmittal, GlTransmittalService $service): JsonResponse
    {
        return (new GlTransmittalResource($service->load($transmittal)))->response();
    }

    public function update(SaveGlTransmittalRequest $request, GlTransmittal $transmittal, GlTransmittalService $service): JsonResponse
    {
        return (new GlTransmittalResource($service->update($transmittal, $request->validated())))->response();
    }

    public function cancel(Request $request, GlTransmittal $transmittal, GlTransmittalService $service): JsonResponse
    {
        abort_unless($this->permissions($request->user('api'))['can_manage'], Response::HTTP_FORBIDDEN);

        return (new GlTransmittalResource($service->cancel($transmittal, $request->user('api'))))->response();
    }

    public function receive(Request $request, GlTransmittal $transmittal, GlTransmittalService $service): JsonResponse
    {
        abort_unless($this->permissions($request->user('api'))['can_receive'], Response::HTTP_FORBIDDEN);
        $data = $request->validate([
            'gl_id' => ['sometimes', 'required', 'integer', 'prohibits:gl_ids'],
            'gl_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:30', 'prohibits:gl_id'],
            'gl_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        return (new GlTransmittalResource($service->receive($transmittal, $request->user('api'), $data['gl_id'] ?? null, $data['gl_ids'] ?? null)))->response();
    }

    public function print(GlTransmittal $transmittal, GlTransmittalPdfService $pdfService): Response
    {
        abort_if($transmittal->cancelled_at !== null, 409, 'A cancelled transmittal cannot be printed.');

        return response($pdfService->render($transmittal), Response::HTTP_OK)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="gl-transmittal-'.$transmittal->bundle_no.'.pdf"')
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array{can_manage: bool, can_receive: bool} */
    private function permissions(?User $user): array
    {
        return [
            'can_manage' => $user?->hasAnyRole(['admin', 'glc admin', 'glc staff', 'encoder', 'reviewer', 'glp admin']) ?? false,
            'can_receive' => $user?->hasAnyRole(['admin', 'encoder', 'reviewer', 'glp admin']) ?? false,
        ];
    }
}
