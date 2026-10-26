<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BeneficiaryRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BeneficiaryController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $beneficiaries = $this->paginate(
            Beneficiary::query(), $request, ['full_name'], ['full_name', 'created_at'],
        );

        return BeneficiaryResource::collection($beneficiaries)->response();
    }

    public function store(BeneficiaryRequest $request): JsonResponse
    {
        $beneficiary = Beneficiary::query()->create($request->validated());

        return (new BeneficiaryResource($beneficiary))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Beneficiary $beneficiary): JsonResponse
    {
        return (new BeneficiaryResource($beneficiary))->response();
    }

    public function update(BeneficiaryRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        $beneficiary->update($request->validated());

        return (new BeneficiaryResource($beneficiary->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Beneficiary::query()->select('id', 'full_name')->distinct()->orderBy('full_name')->get());
    }
}
