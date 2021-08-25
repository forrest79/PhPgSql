<?php declare(strict_types = 1);

return PHP_MAJOR_VERSION < 8 ? [] : [
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#Call to function assert\(\) with true will always evaluate to true\.#',
                'path' => __DIR__ . '/src/Db/Result.php',
                'count' => 1,
            ],
            [
                'message' => '#Call to function is_string\(\) with string will always evaluate to true\.#',
                'path' => __DIR__ . '/src/Db/Result.php',
                'count' => 1,
            ],
        ],
    ],
];
