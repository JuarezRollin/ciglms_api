<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\OfficeRequest;
use App\Http\Resources\OfficeResource;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Models\Office;

class OfficeController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $assistanceTypes = $this->paginate(Office::query(), $request, ['name'], ['name', 'created_at'], ['is_active']);
        return OfficeResource::collection($assistanceTypes)->response();
    }

    public function store(OfficeRequest $request): JsonResponse
    {
        $office = Office::query()->create($request->validated());

        return (new OfficeResource($office))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Office $office): JsonResponse
    {
        return (new OfficeResource($office))->response();
    }

    public function update(OfficeRequest $request, Office $office): JsonResponse
    {
        $office->update($request->validated());

        return (new OfficeResource($office->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Office::query()->select('id', 'name')->distinct()->orderBy('name')->get());
    }
}
