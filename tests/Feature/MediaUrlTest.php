<?php

namespace Tests\Feature;

use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_media_url_uses_public_disk_by_default(): void
    {
        config(['media.disk' => 'public']);

        $this->assertStringContainsString('/storage/uploads/a.png', media_url('uploads/a.png'));
    }

    public function test_media_url_uses_configured_s3_style_disk(): void
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

        $this->assertSame('https://media.jyu1999.com/uploads/a.png', media_url('uploads/a.png'));
    }
}
