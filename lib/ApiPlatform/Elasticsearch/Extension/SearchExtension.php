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

namespace Rollerworks\Component\Search\ApiPlatform\Elasticsearch\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Elastica\Client;
use Elastica\Document;
use Elastica\Search;
use Rollerworks\Component\Search\ApiPlatform\ArrayKeysValidator;
use Rollerworks\Component\Search\Elasticsearch\ElasticsearchFactory;
use Rollerworks\Component\Search\Exception\BadMethodCallException;
use Rollerworks\Component\Search\SearchCondition;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SearchExtension.
 */
class SearchExtension implements QueryCollectionExtensionInterface
{
    /** @var array<string, string[]> */
    private array $identifierNames = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ManagerRegistry $registry,
        private readonly ElasticsearchFactory $elasticsearchFactory,
        private readonly Client $client,
    ) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();

        /** @var SearchCondition|null $condition */
        $condition = $request->attributes->get('_api_search_condition');

        if ($condition === null) {
            return;
        }

        $searchContext = $request->attributes->get('_api_search_context');
        $configuration = $request->attributes->get('_api_search_config');
        $configPath = "{$resourceClass}#attributes[rollerworks_search][contexts][{$searchContext}][elasticsearch]";

        if (empty($configuration['elasticsearch'])) {
            return;
        }

        $configuration = (array) $configuration['elasticsearch'];
        ArrayKeysValidator::assertOnlyKeys($configuration, ['mappings', 'identifiers_normalizer'], $configPath);

        // this snippet looks weird, factory should create the proper instance on its own
        $conditionGenerator = $this->elasticsearchFactory->createCachedConditionGenerator(
            $this->elasticsearchFactory->createConditionGenerator($condition)
        );

        foreach ($configuration['mappings'] as $fieldName => $mapping) {
            $conditions = [];

            if (\is_array($mapping)) {
                ArrayKeysValidator::assertOnlyKeys($mapping, ['property', 'conditions', 'options'], $configPath . '[' . $fieldName . ']');
                ArrayKeysValidator::assertKeysExists($mapping, ['property'], $configPath . '[' . $fieldName . ']');

                $conditionMappings = $mapping['conditions'] ?? [];

                foreach ($conditionMappings as $idx => $conditionMapping) {
                    ArrayKeysValidator::assertOnlyKeys($conditionMapping, ['property', 'value'], $configPath . '[' . $fieldName . '][conditions][' . $idx . ']');

                    $conditions[$conditionMapping['property']] = $conditionMapping['value'];
                }
                $mapping = $mapping['property'];
            }

            $conditionGenerator->registerField($fieldName, $mapping, $conditions, $mapping['options'] ?? []);
        }

        $normalizer = null;

        if (\array_key_exists('identifiers_normalizer', $configuration)) {
            $normalizer = $configuration['identifiers_normalizer'];

            if (! \is_callable($normalizer)) {
                throw new BadMethodCallException('Parameter "identifiers_normalizer" must be a valid callable.');
            }
        }

        $query = $conditionGenerator->getQuery();

        // move limit/offset from QueryBuilder to Elasticsearch query
        $firstResult = ($queryBuilder->getFirstResult() ?: 1);

        if ($firstResult !== 1) {
            $query->setFrom($firstResult);
            $queryBuilder->setFirstResult(null);
        }

        $maxResults = $queryBuilder->getMaxResults();

        if ($maxResults !== null) {
            $query->setSize($maxResults);
            $queryBuilder->setMaxResults(null);
        }

        $search = new Search($this->client);
        $mappings = $conditionGenerator->getMappings();

        foreach ($mappings as $mapping) {
            $index = $this->client->getIndex($mapping->indexName);
            $search->addIndex($index);
        }

        $response = $search->search($query);

        // NOTE: written like this so we only check if we have a normalizer once
        if ($normalizer !== null) {
            $callable = static fn (Document $document) => $normalizer($document->getId());
        } else {
            $callable = static fn (Document $document): ?string => $document->getId();
        }
        $ids = array_map($callable, $response->getDocuments());

        // straight from FOS Elastica Bundle
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $identifier = $this->getIdentifierNames($resourceClass);

        // TODO: hack, only works for non-composite PKs
        $identifier = current($identifier);
        $queryBuilder
            ->andWhere(
                $queryBuilder
                    ->expr()
                        ->in($rootAlias . '.' . $identifier, ':ids')
            )
            ->setParameter('ids', $ids)
        ;

        $this->generateOrderByClause($queryBuilder, $rootAlias . '.' . $identifier, $ids);
    }

    private function getIdentifierNames(string $class): array
    {
        if (! \array_key_exists($class, $this->identifierNames)) {
            $manager = $this->registry->getManagerForClass($class);
            $metadata = $manager->getClassMetadata($class);

            $this->identifierNames[$class] = $metadata->getIdentifier();
        }

        return $this->identifierNames[$class];
    }

    private function generateOrderByClause(QueryBuilder $queryBuilder, string $identifier, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $clause = ['CASE'];
        $last = 0;

        foreach ($ids as $idx => $id) {
            $alias = \sprintf('id%1$s', $idx);
            $queryBuilder->setParameter($alias, $id);
            $clause[] = \sprintf('WHEN %1$s = :%2$s THEN %3$d', $identifier, $alias, $idx);
            ++$last;
        }
        $clause[] = \sprintf('ELSE %1$d', $last);
        $clause[] = 'END';
        $clause[] = 'AS HIDDEN order_by';

        $queryBuilder
            ->addSelect(implode(' ', $clause))
            ->orderBy('order_by', 'ASC')
        ;
    }
}
