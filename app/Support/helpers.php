<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('media_url')) {
    /**
     * Public URL for a stored media path, resolved against the configured media disk.
     */
    function media_url(string $path): string
    {
        return Storage::disk(config('media.disk', 'public'))->url($path);
    }
}
