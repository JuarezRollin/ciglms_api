<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $account = $this->paginate(User::query(), $request, ['name', 'initials', 'position'], ['name', 'created_at'], ['is_active']);

        return AccountResource::collection($account)->response();
    }

    public function store(AccountRequest $request): JsonResponse
    {
        $account = User::query()->create($request->validated());

        return (new AccountResource($account))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $account): JsonResponse
    {
        return (new AccountResource($account))->response();
    }

    public function update(AccountRequest $request, User $account): JsonResponse
    {
        $account->update($request->validated());

        return (new AccountResource($account->refresh()))->response();
    }

    public function updatePassword(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->only('password', 'password_confirmation'),[
            'password' => ['required', 'max:12', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'required|same:password',
        ]);
        $account = User::find($id);
        $account->update($validator->validated());

        return (new AccountResource($account->refresh()))->response();

    }

    public function deactivate(User $account, $id) :JsonResponse
    {
        $Id = (int)$id;
        $account = User::find($Id);
        $account->update(['is_active' => true]);

        return (new AccountResource($account->refresh()))->response();
    }
}
