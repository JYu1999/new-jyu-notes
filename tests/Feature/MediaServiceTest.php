<?php

namespace Tests\Feature;

use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_on_configured_media_disk(): void
    {
        config(['media.disk' => 'mediafake']);
        Storage::fake('mediafake');

        $service = app(MediaService::class);
        $media = $service->upload(UploadedFile::fake()->image('photo.jpg', 10, 10));

        Storage::disk('mediafake')->assertExists($media->path);
    }

    public function test_register_local_file_stores_on_configured_media_disk(): void
    {
        config(['media.disk' => 'mediafake']);
        Storage::fake('mediafake');

        $fakeFile = UploadedFile::fake()->image('legacy.png', 8, 8);
        $source = $fakeFile->getRealPath();

        $service = app(MediaService::class);
        $media = $service->registerLocalFile($source, 'imports/posts/demo');

        $this->assertNotNull($media);
        Storage::disk('mediafake')->assertExists($media->path);
    }

    public function test_delete_removes_file_from_configured_media_disk(): void
    {
        config(['media.disk' => 'mediafake']);
        Storage::fake('mediafake');

        $service = app(MediaService::class);
        $media = $service->upload(UploadedFile::fake()->image('to-delete.jpg', 10, 10));

        $service->delete($media->id);

        Storage::disk('mediafake')->assertMissing($media->path);
        $this->assertModelMissing($media);
    }
}
