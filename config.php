<?php

// See https://hupkit.dev/config.html#initialize-configuration-for-a-repository

return [
    'schema_version' => 2,
    'main_branch' => 'main',
    'branches' => [
        ':default' => [
            'sync-tags' => true,
            'split' => [
                'lib/ApiPlatform' => 'git@github.com:rollersearch/search-api-platform.git',
                'lib/Core' => 'git@github.com:rollersearch/search-core.git',
                'lib/Doctrine/Dbal' => 'git@github.com:rollersearch/search-doctrine-dbal.git',
                'lib/Doctrine/Orm' => 'git@github.com:rollersearch/search-doctrine-orm.git',
                'lib/Elasticsearch' => 'git@github.com:rollersearch/search-elasticsearch.git',
                'lib/Symfony/SearchBundle' => 'git@github.com:rollersearch/search-bundle.git',
                'lib/Symfony/Validator' => 'git@github.com:rollersearch/search-symfony-validator.git',
            ],
        ],
        '1.x' => false,
    ],
    'pull_request' => [
        'split' => 'changed-only',
    ],
];
