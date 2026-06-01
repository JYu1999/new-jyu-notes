<?php

namespace Tests\Feature;

use Tests\TestCase;

class NoHardcodedStorageUrlTest extends TestCase
{
    public function test_no_hardcoded_storage_urls_in_views(): void
    {
        $offenders = [];
        $dir = resource_path('views');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($rii as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (preg_match("/asset\\(['\"]storage\\//", $contents)
                || str_contains($contents, "'/storage/'")
                || str_contains($contents, '"/storage/"')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, "Hardcoded storage URLs found in:\n".implode("\n", $offenders));
    }
}
