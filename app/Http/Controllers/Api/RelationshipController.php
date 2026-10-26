<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\RelationshipRequest;
use App\Http\Resources\RelationshipResource;
use App\Models\Relationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RelationshipController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $relationships = $this->paginate(
            Relationship::query(), $request, ['name'], ['name', 'created_at'],
        );

        return RelationshipResource::collection($relationships)->response();
    }

    public function store(RelationshipRequest $request): JsonResponse
    {
        $relationship = Relationship::query()->create($request->validated());

        return (new RelationshipResource($relationship))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Relationship $relationship): JsonResponse
    {
        return (new RelationshipResource($relationship))->response();
    }

    public function update(RelationshipRequest $request, Relationship $relationship): JsonResponse
    {
        $relationship->update($request->validated());

        return (new RelationshipResource($relationship->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Relationship::query()->select('id', 'name')->distinct()->orderBy('name')->get());
    }
}
