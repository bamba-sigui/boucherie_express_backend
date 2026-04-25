<?php

return [
    'paths' => [
        resource_path('views'),
    ],
    'compiled' => env(
        'VIEW_COMPILED',
        realpath(storage_path('framework/views'))
    ),
];