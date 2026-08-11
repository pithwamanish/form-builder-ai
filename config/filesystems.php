<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Supported Disks: "public", "cloudinary", "s3", "local"
    | When set to "cloudinary", file uploads transparently stream to Cloudinary Cloud Storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'cloudinary' => [
            'driver' => 'cloudinary',
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME', ''),
            'api_key' => env('CLOUDINARY_API_KEY', ''),
            'api_secret' => env('CLOUDINARY_API_SECRET', ''),
            'url' => env('CLOUDINARY_URL', ''),
            'throw' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID', ''),
            'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY', ''),
            'region' => 'us-east-1',
            'bucket' => env('CLOUDFLARE_R2_BUCKET', ''),
            'url' => env('CLOUDFLARE_R2_URL', ''),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT', ''),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
