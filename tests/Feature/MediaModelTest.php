<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_matches_media_url_helper(): void
    {
        config([
            'media.disk' => 'r2test',
            'filesystems.disks.r2test' => [
                'driver' => 's3',
                'key' => 'dummy',
                'secret' => 'dummy',
                'region' => 'auto',
                'bucket' => 'dummy-bucket',
                'url' => 'https://media.jyu1999.com',
                'endpoint' => 'https://acct.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => true,
            ],
        ]);

        $media = Media::create([
            'path' => 'uploads/2026/06/x.png',
            'mime_type' => 'image/png',
            'size' => 1,
            'original_filename' => 'x.png',
        ]);

        $this->assertSame('https://media.jyu1999.com/uploads/2026/06/x.png', $media->url());
    }
}
