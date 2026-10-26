<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Resources\StatusResource;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StatusController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $statuses = $this->paginate(
            Status::query(), $request, ['name'], ['name', 'created_at'], ['is_active'],
        );

        return StatusResource::collection($statuses)->response();
    }

    public function store(StatusRequest $request): JsonResponse
    {
        $status = Status::query()->create($request->validated());

        return (new StatusResource($status))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Status $status): JsonResponse
    {
        return (new StatusResource($status))->response();
    }

    public function update(StatusRequest $request, Status $status): JsonResponse
    {
        $status->update($request->validated());

        return (new StatusResource($status->refresh()))->response();
    }
}
