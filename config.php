<?php

// See https://hupkit.github.io/hupkit/config.html#local-configuration

return [
    'schema_version' => 2,
    'main_branch' => 'main',
    'branches' => [
        ':default' => [
            'sync-tags' => true,
            'split' => [
                'lib/ApiPlatform' => 'git@github.com:rollerworks/search-api-platform.git',
                'lib/Core' => 'git@github.com:rollerworks/search-core.git',
                'lib/Doctrine/Dbal' => 'git@github.com:rollerworks/search-doctrine-dbal.git',
                'lib/Doctrine/Orm' => 'git@github.com:rollerworks/search-doctrine-orm.git',
                'lib/Elasticsearch' => 'git@github.com:rollerworks/search-elasticsearch.git',
                'lib/Symfony/SearchBundle' => 'git@github.com:rollerworks/RollerworksSearchBundle.git',
                'lib/Symfony/Validator' => 'git@github.com:rollerworks/search-symfony-validator.git',
            ],
        ],
        '1.x' => false,
    ],
    'pull_request' => [
        'split' => 'changed-only',
    ],
];
