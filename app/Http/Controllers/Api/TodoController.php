<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Todo\StoreRequest;
use App\Http\Requests\Api\Todo\UpdateRequest;
use App\Models\Todo;
use App\Services\TodoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TodoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Todo::query()->latest()->get()]);
    }

    public function show(Todo $todo): JsonResponse
    {
        return response()->json(['data' => $todo]);
    }

    public function store(StoreRequest $request, TodoService $service): JsonResponse
    {
        $todo = $service->create($request->validated());

        return response()->json(['data' => $todo], 201);
    }

    public function update(Todo $todo, UpdateRequest $request, TodoService $service): JsonResponse
    {
        $todo = $service->update($todo, $request->validated());

        return response()->json(['data' => $todo]);
    }

    public function destroy(Todo $todo, TodoService $service): Response
    {
        $service->delete($todo);

        return response()->noContent(); // 204, empty body
    }
}
