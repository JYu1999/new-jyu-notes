<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tag\StoreRequest;
use App\Http\Requests\Api\Tag\UpdateRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()->with('translations')->latest()->paginate(20)
        );
    }

    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag->load('translations'));
    }

    public function store(StoreRequest $request, TagService $service): JsonResponse
    {
        $tag = $service->create($request->validated());

        return (new TagResource($tag->load('translations')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Tag $tag, UpdateRequest $request, TagService $service): TagResource
    {
        $tag = $service->update($tag, $request->validated());

        return new TagResource($tag->load('translations'));
    }

    public function destroy(Tag $tag, TagService $service): Response
    {
        $service->delete($tag);

        return response()->noContent();
    }
}
