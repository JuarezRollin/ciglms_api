<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $clients = $this->paginate(
            Client::query(), $request, ['full_name', 'address'], ['full_name', 'created_at'], ['sex'],
        );

        return ClientResource::collection($clients)->response();
    }

    public function store(ClientRequest $request): JsonResponse
    {
        $client = Client::query()->create($request->validated());

        return (new ClientResource($client))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Client $client): JsonResponse
    {
        return (new ClientResource($client))->response();
    }

    public function update(ClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return (new ClientResource($client->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Client::query()->select('id', 'full_name')->distinct()->orderBy('full_name')->get());
    }
}
