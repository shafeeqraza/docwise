<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Vector Store Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default vector store driver that will be used
    | by the application. You may change this to any of the drivers defined
    | in the "drivers" array below.
    |
    | Supported: "qdrant", "pgsql"
    |
    */

    'default' => env('VECTOR_STORE_DRIVER', 'qdrant'),

    /*
    |--------------------------------------------------------------------------
    | Vector Store Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each vector store driver.
    | Each driver has its own configuration options.
    |
    */

    'drivers' => [

        'qdrant' => [
            'driver' => 'qdrant',
            'host' => env('QDRANT_HOST', 'localhost'),
            'port' => env('QDRANT_PORT', 6333),
            'api_key' => env('QDRANT_API_KEY'),
            'timeout' => env('QDRANT_TIMEOUT', 30),
            'default_distance' => env('QDRANT_DISTANCE', 'Cosine'),
            'collection_name' => env('QDRANT_COLLECTION', 'documents'),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'table' => 'document_chunks',
            'vector_column' => 'embedding',
            'default_dimension' => env('VECTOR_DIMENSION', 1536),
        ],

    ],

];
