<?php

declare(strict_types=1);

return [
    'roles' => [
        'administrator' => [
            'name' => 'Administrator',
            'capabilities' => ['*'],
        ],
        'editor' => [
            'name' => 'Editor',
            'capabilities' => [
                'post:create', 'post:edit', 'post:delete', 'post:publish',
                'page:create', 'page:edit', 'page:delete', 'page:publish',
                'media:upload', 'media:delete',
                'category:manage', 'tag:manage',
            ],
        ],
        'author' => [
            'name' => 'Author',
            'capabilities' => [
                'post:create', 'post:edit_own', 'post:delete_own', 'post:publish',
                'media:upload',
            ],
        ],
        'contributor' => [
            'name' => 'Contributor',
            'capabilities' => [
                'post:create', 'post:edit_own',
            ],
        ],
        'subscriber' => [
            'name' => 'Subscriber',
            'capabilities' => [
                'profile:edit', 'comment:create',
            ],
        ],
    ],
];
