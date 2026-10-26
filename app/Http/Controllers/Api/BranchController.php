<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $branches = $this->paginate(
            Branch::query()->with('serviceProvider'),
            $request,
            ['name', 'address', 'branch_addressee'],
            ['name', 'created_at'],
            ['service_provider_id', 'is_active'],
        );

        return BranchResource::collection($branches)->response();
    }

    public function store(BranchRequest $request): JsonResponse
    {
        $branch = Branch::query()->create($request->validated())->load('serviceProvider');

        return (new BranchResource($branch))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Branch $branch): JsonResponse
    {
        return (new BranchResource($branch->load('serviceProvider')))->response();
    }

    public function update(BranchRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return (new BranchResource($branch->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, Branch $branch): JsonResponse
    {
        $branch->update($request->validated());

        return (new BranchResource($branch->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Branch::query()->select('id', 'name', 'service_provider_id')->where('is_active', true)->orderBy('name')->get());
    }
}
