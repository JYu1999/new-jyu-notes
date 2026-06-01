<?php

return [
    /*
    | The filesystem disk used to store and serve uploaded/imported media.
    | Local dev: "public" (storage/app/public via /storage symlink).
    | Production/import: "s3" (Cloudflare R2, served via media.jyu1999.com).
    */
    'disk' => env('MEDIA_DISK', 'public'),
];
