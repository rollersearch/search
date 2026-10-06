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

namespace Rollerworks\Component\Search\Extension;

use Psr\Container\ContainerInterface;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Field\FieldType;
use Rollerworks\Component\Search\Field\FieldTypeExtension;
use Rollerworks\Component\Search\Loader\ClosureContainer;
use Rollerworks\Component\Search\SearchExtension;

/**
 * Provides a way to lazy load types from the Container.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class LazyExtension implements SearchExtension
{
    /**
     * @param array<string, array<string, FieldTypeExtension>> $typeExtensionServices
     */
    public function __construct(
        private readonly ContainerInterface $typeContainer,
        private array $typeExtensionServices,
    ) {}

    /**
     * Creates a new LazyExtension with easy factories for lazy loading.
     *
     * @param array<string, \Closure>                          $types          FQCN => \Closure factory
     * @param array<string, array<string, FieldTypeExtension>> $typeExtensions
     */
    public static function create(array $types, array $typeExtensions = []): self
    {
        return new self(new ClosureContainer($types), $typeExtensions);
    }

    public function getType(string $name): FieldType
    {
        if (! $this->typeContainer->has($name)) {
            throw new InvalidArgumentException(
                \sprintf('The field type "%s" is not registered with the service container.', $name)
            );
        }

        return $this->typeContainer->get($name);
    }

    public function hasType(string $name): bool
    {
        return $this->typeContainer->has($name);
    }

    public function getTypeExtensions(string $name): array
    {
        $extensions = [];

        foreach ($this->typeExtensionServices[$name] ?? [] as $extensionId => $extension) {
            $extensions[] = $extension;

            // validate result of getExtendedType() to ensure it is consistent with the service definition
            if ($extension->getExtendedType() !== $name) {
                throw new InvalidArgumentException(
                    \sprintf(
                        'The extended type specified for the service "%s" does not match the actual extended type. Expected "%s", given "%s".',
                        $extensionId,
                        $name,
                        $extension->getExtendedType()
                    )
                );
            }
        }

        return $extensions;
    }
}
