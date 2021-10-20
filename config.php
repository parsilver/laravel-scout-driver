<?php

return [

    'app-search' => [
        'endpoint' => env('APPSEARCH_ENDPOINT'),
        'key' => env('APPSEARCH_API_KEY'),
        'engine' => [
            'language' => env('APPSEARCH_ENGINE_LANGUAGE'),
        ],
    ],
];