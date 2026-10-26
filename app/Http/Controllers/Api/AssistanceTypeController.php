<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssistanceTypeRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\AssistanceTypeResource;
use App\Models\AssistanceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AssistanceTypeController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $assistanceTypes = $this->paginate(AssistanceType::query(), $request, ['name'], ['name', 'created_at'], ['is_active']);
        return AssistanceTypeResource::collection($assistanceTypes)->response();
    }

    public function store(AssistanceTypeRequest $request): JsonResponse
    {
        $assistanceType = AssistanceType::query()->create($request->validated());

        return (new AssistanceTypeResource($assistanceType))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(AssistanceType $assistanceType): JsonResponse
    {
        return (new AssistanceTypeResource($assistanceType))->response();
    }

    public function update(AssistanceTypeRequest $request, AssistanceType $assistanceType): JsonResponse
    {
        $assistanceType->update($request->validated());

        return (new AssistanceTypeResource($assistanceType->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, AssistanceType $assistanceType): JsonResponse
    {
        $assistanceType->update($request->validated());

        return (new AssistanceTypeResource($assistanceType->refresh()))->response();
    }
    
    public function dropdown(): JsonResponse
    {
        return response()->json(AssistanceType::query()->select('id', 'name')->distinct()->where('is_active', true)->orderBy('name')->get());
    }
}
