<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\ApiPlatform\Doctrine\Orm\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Rollerworks\Component\Search\ApiPlatform\ArrayKeysValidator;
use Rollerworks\Component\Search\Doctrine\Orm\ConditionGenerator;
use Rollerworks\Component\Search\Doctrine\Orm\DoctrineOrmFactory;
use Rollerworks\Component\Search\SearchCondition;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Applies the RollerworksSearch SearchCondition on a resource query.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class SearchExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DoctrineOrmFactory $ormFactory,
    ) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request) {
            return;
        }

        /** @var SearchCondition|null $condition */
        $condition = $request->attributes->get('_api_search_condition');

        if ($condition === null) {
            return;
        }

        $searchContext = $request->attributes->get('_api_search_context');
        $configuration = $request->attributes->get('_api_search_config');
        $configPath = "{$resourceClass}#attributes[rollerworks_search][contexts][{$searchContext}][doctrine_orm]";

        if (empty($configuration['doctrine_orm'])) {
            return;
        }

        $configuration = $configuration['doctrine_orm'];
        ArrayKeysValidator::assertOnlyKeys($configuration, ['relations', 'mappings'], $configPath);
        $this->configureRelations($configPath, $configuration, $queryBuilder);

        $conditionGenerator = $this->ormFactory->createCachedConditionGenerator($queryBuilder, $condition);

        $this->configureMappings($resourceClass, $configuration, $configPath, $conditionGenerator);
        $conditionGenerator->apply();
    }

    private function configureRelations(string $configPath, array $configuration, QueryBuilder $queryBuilder): void
    {
        if (empty($configuration['relations'])) {
            return;
        }

        $configPath .= '[relations]';

        if (isset($configuration['relations']['o'])) {
            throw new RuntimeException(\sprintf('Invalid configuration for "%s", relation name "o" is already used for the root.', $configPath));
        }

        foreach ($configuration['relations'] as $alias => $config) {
            $path = "{$configPath}[{$alias}]";

            ArrayKeysValidator::assertOnlyKeys($config, ['join', 'entity', 'type', 'conditionType', 'condition', 'index'], $path);
            ArrayKeysValidator::assertKeysExists($config, ['join', 'entity'], $path);

            if (! method_exists($queryBuilder, ($config['type'] ??= 'left') . 'Join')) {
                throw new RuntimeException(\sprintf('Invalid value for "%s", type "%s" is not supported. Use left, right or inner.', $path . '[type]', $config['type']));
            }

            $queryBuilder->{$config['type'] . 'Join'}(
                $config['join'],
                $alias,
                $config['conditionType'] ?? null,
                $config['condition'] ?? null,
                $config['index'] ?? null
            );
        }
    }

    private function configureMappings(string $resourceClass, array $configuration, string $configPath, ConditionGenerator $conditionGenerator): void
    {
        $configuration['relations']['o']['entity'] = $resourceClass;

        foreach ($configuration['mappings'] as $mappingName => $mapping) {
            if (! \is_array($mapping)) {
                $mapping = [
                    'alias' => 'o',
                    'property' => $mapping,
                ];
            }

            if (empty($mapping['alias'])) {
                $mapping['alias'] = 'o';
            }

            $path = "{$configPath}[mappings][{$mappingName}]";
            ArrayKeysValidator::assertOnlyKeys($mapping, ['property', 'alias', 'type'], $path);
            ArrayKeysValidator::assertKeysExists($mapping, ['property'], $path);

            if (! isset($configuration['relations'][$mapping['alias']])) {
                throw new RuntimeException(\sprintf('Invalid value for "%s", alias "%s" is not registered in the "relations".', $path . '[alias]', $mapping['alias']));
            }

            $conditionGenerator->setField(
                $mappingName,
                $mapping['property'],
                $mapping['alias'],
                $configuration['relations'][$mapping['alias']]['entity'],
                $mapping['type'] ?? null
            );
        }
    }
}
