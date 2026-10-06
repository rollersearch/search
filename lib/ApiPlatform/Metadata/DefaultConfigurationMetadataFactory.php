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

namespace Rollerworks\Component\Search\ApiPlatform\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * The DefaultConfigurationMetadataFactory merges the `_defaults` configuration
 * of the `rollerworks_search` resource attribute to all configs.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class DefaultConfigurationMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {}

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        /** @var ApiResource $resourceMetadata */
        foreach ($resourceMetadataCollection as $i => $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();

            if ($operations === null) {
                continue;
            }

            /** @var Operation $operation */
            foreach ($resourceMetadata->getOperations() as $operationName => $operation) {
                $extraProperties = $operation->getExtraProperties();

                if (isset($extraProperties['rollerworks_search']['contexts']['_defaults'])) {
                    $extraProperties['rollerworks_search'] = $this->mergeSearchContexts($extraProperties['rollerworks_search']);
                    $operations->add($operationName, $operation->withExtraProperties($extraProperties));
                }
            }

            $resourceMetadataCollection[$i] = $resourceMetadata->withOperations($operations);
        }

        return $resourceMetadataCollection;
    }

    private function mergeSearchContexts(array $searchConfig): array
    {
        $defaults = $searchConfig['contexts']['_defaults'];
        unset($searchConfig['contexts']['_defaults']);

        foreach ($searchConfig['contexts'] as $name => $configuration) {
            $searchConfig['contexts'][$name] = array_replace_recursive($defaults, $configuration);
        }

        return $searchConfig;
    }
}
