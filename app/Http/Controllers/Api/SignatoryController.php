<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\SignatoryRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\SignatoryResource;
use App\Models\Signatory;
use App\Services\SignatoryCredentialService;
use App\Services\SignatoryRoutePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SignatoryController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $signatories = $this->paginate(
            Signatory::query(),
            $request,
            ['name', 'initials', 'designation'],
            ['name', 'minimum_amount', 'maximum_amount', 'created_at'],
            ['is_active'],
        );

        return SignatoryResource::collection($signatories)->response();
    }

    public function store(SignatoryRequest $request, SignatoryCredentialService $credentials): JsonResponse
    {
        $signatory = $credentials->save(new Signatory, $request->validated());

        return (new SignatoryResource($signatory))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Signatory $signatory): JsonResponse
    {
        return (new SignatoryResource($signatory))->response();
    }

    public function update(SignatoryRequest $request, Signatory $signatory, SignatoryCredentialService $credentials): JsonResponse
    {
        $signatory = $credentials->save($signatory, $request->validated());

        return (new SignatoryResource($signatory->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, Signatory $signatory): JsonResponse
    {
        $signatory->update($request->validated());

        return (new SignatoryResource($signatory->refresh()))->response();
    }

    public function dropdown(Request $request): JsonResponse
    {
        $gl_amount = $request->input('gl_amount', 0);

        $signatory = Signatory::query()
            ->select('id', 'name', 'initials')
            ->distinct()
            ->where('is_active', true)
            ->orderBy('name')
            ->when($gl_amount != 0, function ($query) use ($gl_amount) {
                return $query
                    ->where('minimum_amount', '<=', $gl_amount)
                    ->where('maximum_amount', '>=', $gl_amount);
            })
            ->get();

        return response()->json($signatory);
    }

    public function routingPreview(Request $request, SignatoryRoutePlanner $routePlanner): JsonResponse
    {
        $data = $request->validate([
            'gl_amount' => ['required', 'numeric', 'min:0'],
            'signatory_id' => ['required', 'integer', 'exists:signatories,id'],
        ]);
        $finalSignatory = Signatory::query()->findOrFail($data['signatory_id']);

        if (! $finalSignatory->is_active
            || $data['gl_amount'] < $finalSignatory->minimum_amount
            || $data['gl_amount'] > $finalSignatory->maximum_amount) {
            throw ValidationException::withMessages(['signatory_id' => 'Select an active signatory authorized for the GL amount.']);
        }

        return response()->json($routePlanner->signatoriesForSelection($data['gl_amount'], $finalSignatory)
            ->values()
            ->map(fn (Signatory $signatory, int $index): array => [
                'id' => $signatory->id,
                'name' => $signatory->name,
                'designation' => $signatory->designation,
                'sequence' => $index + 1,
            ]));
    }
}
