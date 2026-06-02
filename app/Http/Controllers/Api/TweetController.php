<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tweet\StoreRequest;
use App\Http\Requests\Api\Tweet\UpdateRequest;
use App\Http\Resources\TweetResource;
use App\Models\Tweet;
use App\Services\TweetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TweetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Tweet::query()->with(['tags'])->latest();

        if ($request->filled('locale')) {
            $query->where('locale', $request->string('locale'));
        }

        return TweetResource::collection($query->paginate(20));
    }

    public function show(Tweet $tweet): TweetResource
    {
        return new TweetResource($tweet->load(['tags']));
    }

    public function store(StoreRequest $request, TweetService $service): JsonResponse
    {
        $tweet = $service->create($request->validated()); // no status => draft

        return (new TweetResource($tweet->load(['tags'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Tweet $tweet, UpdateRequest $request, TweetService $service): TweetResource
    {
        $tweet = $service->update($tweet, $request->validated()); // validated() has no status
        return new TweetResource($tweet->load(['tags']));
    }

    public function destroy(Tweet $tweet, TweetService $service): Response
    {
        $service->softDelete($tweet);
        return response()->noContent();
    }
}
