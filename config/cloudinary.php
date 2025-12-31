<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudinary file storage service.
    | Get your credentials from: https://cloudinary.com/console
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'secure' => env('CLOUDINARY_SECURE', true),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Default upload settings for document files.
    |
    */

    'upload' => [
        'folder' => env('CLOUDINARY_UPLOAD_FOLDER', 'docwise/documents'),
        'resource_type' => 'auto', // auto-detect: image, video, raw, etc.
        'overwrite' => false,
        'invalidate' => true,
        'use_filename' => false, // Use Cloudinary's generated unique filename
        'unique_filename' => true,
    ],

];
