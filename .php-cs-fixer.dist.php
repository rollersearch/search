<?php

$header = <<<EOF
This file is part of the RollerworksSearch package.

(c) Sebastiaan Stok <s.stok@rollerscapes.net>

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

/** @var \Symfony\Component\Finder\Finder $finder */
$finder = PhpCsFixer\Finder::create();
$finder
    ->in([__DIR__.'/lib'])
    ->notPath('Symfony/SearchBundle/Tests/Functional/Application/var')
    ->exclude('Fixtures')
;

$config = new PhpCsFixer\Config();
$config
        ->setRiskyAllowed(true)
    ->setRules(
        array_merge(
            require __DIR__ . '/vendor/rollerscapes/standards/php-cs-fixer-rules.php',
            [
                'header_comment' => ['header' => $header],
                'single_line_empty_body' => true,
                'php_unit_test_annotation' => ['style' => 'annotation'],
            ])
    )
    ->setFinder($finder);

return $config;
