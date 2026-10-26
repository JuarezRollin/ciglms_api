<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(ListRequest $request): JsonResponse
    {
        $categories = $this->paginate(
            Category::query(), $request, ['name', 'description'], ['name', 'created_at'],
        );

        return CategoryResource::collection($categories)->response();
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        return (new CategoryResource($category))->response();
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return (new CategoryResource($category->refresh()))->response();
    }

    public function dropdown(): JsonResponse
    {
        return response()->json(Category::query()->select('id', 'name')->distinct()->orderBy('name')->get());
    }
}
