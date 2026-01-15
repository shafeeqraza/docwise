<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qdrant Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Qdrant vector database connection.
    |
    */

    'host' => env('QDRANT_HOST', 'localhost'),
    'port' => env('QDRANT_PORT', 6333),
    'api_key' => env('QDRANT_API_KEY'),
    'timeout' => env('QDRANT_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Collection Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for Qdrant collections.
    |
    */

    'default_distance' => env('QDRANT_DISTANCE', 'Cosine'), // Cosine, Euclidean, Dot

];

