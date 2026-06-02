<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Category\StoreRequest;
use App\Http\Requests\Api\Category\UpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->with('translations')->latest()->paginate(20)
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('translations'));
    }

    public function store(StoreRequest $request, CategoryService $service): JsonResponse
    {
        $category = $service->create($request->validated());

        return (new CategoryResource($category->load('translations')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Category $category, UpdateRequest $request, CategoryService $service): CategoryResource
    {
        $category = $service->update($category, $request->validated());

        return new CategoryResource($category->load('translations'));
    }

    public function destroy(Category $category, CategoryService $service): Response
    {
        $service->delete($category);

        return response()->noContent();
    }
}
