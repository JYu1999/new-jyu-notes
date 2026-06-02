<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Media\StoreRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function index(MediaService $service): AnonymousResourceCollection
    {
        return MediaResource::collection($service->paginate(20));
    }

    public function store(StoreRequest $request, MediaService $service): JsonResponse
    {
        $media = $service->upload($request->file('file'), $request->user());

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Media $media, MediaService $service): Response
    {
        $service->delete($media->id);

        return response()->noContent();
    }
}
