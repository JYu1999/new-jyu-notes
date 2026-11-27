<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Media\StoreRequest;
use App\Services\MediaService;
use App\Services\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request, MediaService $service, MediaUsageService $usage): View
    {
        $unusedOnly = $request->boolean('unused');

        return view('admin.media.index', [
            'media' => $service->paginate(24, $unusedOnly ? $usage->unusedIds() : null),
            'unusedOnly' => $unusedOnly,
        ]);
    }

    public function store(StoreRequest $request, MediaService $service): JsonResponse
    {
        $media = $service->upload($request->file('file'), $request->user());

        return response()->json([
            'id' => $media->id,
            'url' => $media->url(),
            'path' => $media->path,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
        ]);
    }

    public function destroy(int $id, MediaService $service): JsonResponse
    {
        $service->delete($id);

        return response()->json(['ok' => true]);
    }
}
