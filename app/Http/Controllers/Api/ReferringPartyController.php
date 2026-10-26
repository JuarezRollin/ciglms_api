<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\referringPartyRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\referringPartyResource;
use App\Models\ReferringParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReferringPartyController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $referringParty = $this->paginate(ReferringParty::query(), $request, ['name'], ['name', 'created_at'], ['is_active']);
        return referringPartyResource::collection($referringParty)->response();
    }

    public function store(referringPartyRequest $request): JsonResponse
    {
        $referringParty = ReferringParty::query()->create($request->validated());

        return (new referringPartyResource($referringParty))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ReferringParty $referringParty): JsonResponse
    {
        return (new referringPartyResource($referringParty))->response();
    }

    public function update(referringPartyRequest $request, ReferringParty $referringParty): JsonResponse
    {
        $referringParty->update($request->validated());

        return (new referringPartyResource($referringParty->refresh()))->response();
    }

    public function updateStatus(UpdateStatusRequest $request, ReferringParty $referringParty): JsonResponse
    {
        $referringParty->update($request->validated());

        return (new referringPartyResource($referringParty->refresh()))->response();
    }
    
    public function dropdown(): JsonResponse
    {
        return response()->json(ReferringParty::query()->select('id', 'name')->distinct()->where('is_active', true)->orderBy('name')->get());
    }
}
