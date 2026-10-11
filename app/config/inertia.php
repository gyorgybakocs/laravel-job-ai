<?php

// https://github.com/inertiajs/inertia-laravel/issues/718
return [
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['vue'],
    ],
];
