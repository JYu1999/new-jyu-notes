<?php

namespace Tests\Feature;

use App\Console\Commands\ImportFromHugo;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class ImportMediaRewriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_use_media_url_for_configured_disk(): void
    {
        // Configure a local disk that returns an R2-style URL so we can test
        // media_url() resolution without an actual S3/R2 connection.
        $testRoot = sys_get_temp_dir().'/r2test-'.uniqid();
        mkdir($testRoot, 0777, true);

        config([
            'media.disk' => 'r2test',
            'filesystems.disks.r2test' => [
                'driver' => 'local',
                'root' => $testRoot,
                'url' => 'https://media.jyu1999.com',
            ],
        ]);
        // Clear any cached disk instance so the new config is picked up.
        Storage::forgetDisk('r2test');

        // Bundle dir with one real image file.
        $bundle = sys_get_temp_dir().'/hugo-bundle-'.uniqid();
        mkdir($bundle, 0777, true);
        $img = imagecreatetruecolor(4, 4);
        imagepng($img, "{$bundle}/pic.png");
        imagedestroy($img);

        $cmd = app(ImportFromHugo::class);

        // Initialize the command input/output so $this->option() works.
        $input = new ArrayInput([]);
        $input->bind($cmd->getDefinition());
        $cmd->setInput($input);
        $cmd->setOutput(new OutputStyle($input, new NullOutput()));

        $admin = User::create([
            'name' => 'A',
            'email' => 'a@b.c',
            'password' => bcrypt('x'),
            'role' => User::ROLE_ADMIN,
        ]);
        $ref = new ReflectionClass($cmd);
        $adminProp = $ref->getProperty('admin');
        $adminProp->setAccessible(true);
        $adminProp->setValue($cmd, $admin);

        $method = $ref->getMethod('buildAssetRewrites');
        $method->setAccessible(true);
        /** @var array<string,string> $rewrites */
        $rewrites = $method->invoke($cmd, $bundle, 'imports/posts/demo');

        $this->assertArrayHasKey('pic.png', $rewrites);
        $this->assertStringStartsWith('https://media.jyu1999.com/', $rewrites['pic.png']);

        @unlink("{$bundle}/pic.png");
        @rmdir($bundle);
    }
}
