<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\ListRequest;
use App\Http\Requests\SwoRequest;
use App\Http\Resources\SwoResource;
use Illuminate\Http\Response;
use App\Models\Swo;

class SocialWorkerController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $assistanceTypes = $this->paginate(Swo::query(), $request, ['name'], ['name', 'created_at'], ['is_active']);
        return SwoResource::collection($assistanceTypes)->response();
    }

    public function store(SwoRequest $request): JsonResponse
    {
        $swo = Swo::query()->create($request->validated());

        return (new SwoResource($swo))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Swo $swo): JsonResponse
    {
        return (new SwoResource($swo))->response();
    }

    public function update(SwoRequest $request, Swo $swo): JsonResponse
    {
        $swo->update($request->validated());

        return (new SwoResource($swo->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Swo::query()->select('id', 'name')->where('is_active', true)->distinct()->orderBy('name')->get());
    }
}
