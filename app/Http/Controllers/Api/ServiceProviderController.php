<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\ServiceProviderRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\ServiceProviderResource;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ServiceProviderController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $serviceProviders = $this->paginate(
            ServiceProvider::query(),
            $request,
            ['name', 'business_name', 'address', 'addressee', 'designation', 'category', 'jurisdiction'],
            ['name', 'created_at'],
            ['is_active'],
        );

        return ServiceProviderResource::collection($serviceProviders)->response();
    }

    public function store(ServiceProviderRequest $request): JsonResponse
    {
        $serviceProvider = ServiceProvider::query()->create($request->validated());

        return (new ServiceProviderResource($serviceProvider))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ServiceProvider $serviceProvider): JsonResponse
    {
        return (new ServiceProviderResource($serviceProvider->load('branches')))->response();
    }

    public function update(ServiceProviderRequest $request, ServiceProvider $serviceProvider): JsonResponse
    {
        $serviceProvider->update($request->validated());

        return (new ServiceProviderResource($serviceProvider->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, ServiceProvider $serviceProvider): JsonResponse
    {
        $serviceProvider->update($request->validated());

        return (new ServiceProviderResource($serviceProvider->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(ServiceProvider::query()->select('id', 'name', 'addressee', 'designation', 'status', 'sp_remarks')->orderBy('name')->get());
    }
}
