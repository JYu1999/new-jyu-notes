<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Post\StoreRequest;
use App\Http\Requests\Api\Post\UpdateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Post::query()->with(['tags', 'categories'])->latest();

        if ($request->filled('locale')) {
            $query->where('locale', $request->string('locale'));
        }

        return PostResource::collection($query->paginate(20));
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post->load(['tags', 'categories']));
    }

    public function store(StoreRequest $request, PostService $service): JsonResponse
    {
        $post = $service->create($request->validated()); // no status => draft

        return (new PostResource($post->load(['tags', 'categories'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Post $post, UpdateRequest $request, PostService $service): PostResource
    {
        $post = $service->update($post, $request->validated()); // validated() has no status

        return new PostResource($post->load(['tags', 'categories']));
    }

    public function destroy(Post $post, PostService $service): Response
    {
        $service->softDelete($post);

        return response()->noContent();
    }

    public function storeTranslation(Post $post, Request $request, PostService $service): JsonResponse
    {
        $data = $request->validate(['locale' => 'required|string|in:zh,en,ja,vi,id']);
        $translation = $service->createTranslation($post, $data['locale']);

        return (new PostResource($translation->load(['tags', 'categories'])))
            ->response()
            ->setStatusCode(201);
    }

    public function publish(Post $post, PostService $service): PostResource
    {
        $service->updateStatus($post, Post::STATUS_PUBLISHED);

        return new PostResource($post->fresh()->load(['tags', 'categories']));
    }
}
