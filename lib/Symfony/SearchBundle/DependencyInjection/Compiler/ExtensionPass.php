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

namespace Rollerworks\Bundle\SearchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Adds all services with the tags "rollerworks_search.type" and
 * "rollerworks_search.type_extension" as arguments of the
 * "rollerworks_search.extension" service.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class ExtensionPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function __construct(
        private string $fieldExtensionService = 'rollerworks_search.extension',
        private string $fieldTypeTag = 'rollerworks_search.type',
        private string $fieldTypeExtensionTag = 'rollerworks_search.type_extension',
    ) {}

    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition($this->fieldExtensionService)) {
            return;
        }

        $definition = $container->getDefinition($this->fieldExtensionService);
        $definition->replaceArgument(0, $this->processTypes($container));
        $definition->replaceArgument(1, $this->processTypeExtensions($container));
    }

    private function processTypes(ContainerBuilder $container): Definition
    {
        // Get service locator argument
        $servicesMap = [];

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->findTaggedServiceIds($this->fieldTypeTag) as $serviceId => $tag) {
            $serviceDefinition = $container->getDefinition($serviceId);
            // Add field type service to the service locator
            $servicesMap[$serviceDefinition->getClass()] = new ServiceClosureArgument(new Reference($serviceId));
        }

        return (new Definition(ServiceLocator::class, [$servicesMap]))->addTag('container.service_locator');
    }

    private function processTypeExtensions(ContainerBuilder $container): array
    {
        $typeExtensions = [];

        foreach ($this->findAndSortTaggedServices($this->fieldTypeExtensionTag, $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);
            $tag = $serviceDefinition->getTag($this->fieldTypeExtensionTag);

            if (! isset($tag[0]['extended_type'])) {
                throw new InvalidArgumentException(\sprintf('"%s" tagged services must have the extended type configured using the extended_type/extended-type attribute, none was configured for the "%s" service.', $this->fieldTypeExtensionTag, $serviceId));
            }

            $extendedType = $tag[0]['extended_type'];
            $typeExtensions[$extendedType][] = $serviceId;
        }

        $allExtensions = [];

        foreach ($typeExtensions as $extendedType => $extensions) {
            $allExtensions[$extendedType] = new IteratorArgument(array_map(
                static fn ($extensionId): Reference => new Reference($extensionId),
                $extensions
            ));
        }

        return $allExtensions;
    }
}
