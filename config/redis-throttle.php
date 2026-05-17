<?php

return [

    'free' => [
        'per_minute' => 10,
        'per_day' => 1000,
        'file_size' => 5, // MB
    ],

    'pro' => [
        'per_minute' => 100,
        'per_day' => 10000,
        'file_size' => null, // No limit
    ],

    'enterprise' => [
        'per_minute' => 1000,
        'per_day' => 500000,
        'file_size' => null, // No limit
    ],

];
