<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    public function upload(UploadedFile $file, ?User $user = null): Media
    {
        $year = date('Y');
        $month = date('m');
        $path = $file->store("uploads/{$year}/{$month}", config('media.disk'));

        $dimensions = $this->dimensions($file);

        return Media::create([
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $user?->id,
        ]);
    }

    public function delete(int $id): void
    {
        $media = Media::findOrFail($id);
        Storage::disk(config('media.disk'))->delete($media->path);
        $media->delete();
    }

    public function paginate(int $perPage = 24): LengthAwarePaginator
    {
        return Media::query()->latest()->paginate($perPage);
    }

    /**
     * Create or fetch a Media record by storing an arbitrary file path (used by migration seeder).
     */
    public function registerLocalFile(string $sourcePath, ?string $subdir = null, ?User $user = null): ?Media
    {
        if (! is_file($sourcePath)) {
            return null;
        }

        $year = date('Y');
        $month = date('m');
        $folder = $subdir ?: "uploads/{$year}/{$month}";
        $filename = basename($sourcePath);
        $targetPath = "{$folder}/{$filename}";

        Storage::disk(config('media.disk'))->put($targetPath, file_get_contents($sourcePath));

        $existing = Media::where('path', $targetPath)->first();
        if ($existing) return $existing;

        $size = filesize($sourcePath) ?: 0;
        $mime = mime_content_type($sourcePath) ?: 'application/octet-stream';

        $width = null;
        $height = null;
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($sourcePath);
            if ($info !== false) {
                $width = $info[0];
                $height = $info[1];
            }
        }

        return Media::create([
            'path' => $targetPath,
            'mime_type' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'original_filename' => $filename,
            'uploaded_by' => $user?->id,
        ]);
    }

    private function dimensions(UploadedFile $file): array
    {
        $width = null;
        $height = null;

        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $info = @getimagesize($file->getRealPath());
            if ($info !== false) {
                $width = $info[0];
                $height = $info[1];
            }
        }

        return ['width' => $width, 'height' => $height];
    }
}
