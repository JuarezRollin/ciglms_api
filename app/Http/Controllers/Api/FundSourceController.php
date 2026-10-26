<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FundSourceRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\FundSourceResource;
use App\Models\FundSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FundSourceController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $fundSources = $this->paginate(
            FundSource::query(), $request, ['name'], ['name', 'amount', 'created_at'], ['is_active'],
        );

        return FundSourceResource::collection($fundSources)->response();
    }

    public function store(FundSourceRequest $request): JsonResponse
    {
        $fundSource = FundSource::query()->create($request->validated());

        return (new FundSourceResource($fundSource))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(FundSource $fundSource): JsonResponse
    {
        return (new FundSourceResource($fundSource))->response();
    }

    public function update(FundSourceRequest $request, FundSource $fundSource): JsonResponse
    {
        $fundSource->update($request->validated());

        return (new FundSourceResource($fundSource->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, FundSource $fundSource): JsonResponse
    {
        $fundSource->update($request->validated());

        return (new FundSourceResource($fundSource->refresh()))->response();
    }
}
