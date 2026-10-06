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

namespace Rollerworks\Component\Search;

use Psr\Container\ContainerInterface;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Loader\ClosureContainer;

/**
 * LazyFieldSetRegistry tries to lazily load the FieldSetConfigurator
 * from s PSR-11 compatible Container or by FQCN.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class LazyFieldSetRegistry implements FieldSetRegistry
{
    /** @var array<string, FieldSetConfigurator> */
    private array $configurators = [];

    /**
     * Constructor.
     *
     * Note you don't have to register the Configurator when it has no constructor
     * or setter dependencies. You can simple use the FQCN of the configurator class,
     * and will be initialized upon first usage.
     *
     * @param ContainerInterface    $container  A Service locator able to lazily load
     *                                          the FieldSet configurators
     * @param array<string, string> $serviceIds Configurator name (FQCN) to service-id mapping
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $serviceIds,
    ) {}

    /**
     * Creates a new LazyFieldSetRegistry with easy factories for loading.
     *
     * @param array<string, \Closure> $configurators an array of lazy loading configurators.
     *                                               The Closure when called is expected to return
     *                                               a {@see FieldSetConfigurator} object
     */
    public static function create(array $configurators = []): self
    {
        $names = array_keys($configurators);

        return new self(new ClosureContainer($configurators), array_combine($names, $names));
    }

    /**
     * @param string $name The name of the FieldSet configurator
     *
     * @throws InvalidArgumentException if the configurator can not be retrieved
     */
    public function getConfigurator(string $name): FieldSetConfigurator
    {
        if (isset($this->configurators[$name])) {
            return $this->configurators[$name];
        }

        if (isset($this->serviceIds[$name])) {
            $configurator = $this->container->get($this->serviceIds[$name]);
        } elseif (class_exists($name)) {
            // Support fully-qualified class names.
            $configurator = new $name();
        } else {
            throw new InvalidArgumentException(\sprintf('Could not load FieldSet configurator "%s".', $name));
        }

        if (! $configurator instanceof FieldSetConfigurator) {
            throw new InvalidArgumentException(\sprintf('Configurator class "%s" is expected to be an instance of ' . FieldSetConfigurator::class, $name));
        }

        return $this->configurators[$name] = $configurator;
    }

    /**
     * Returns whether the given FieldSetConfigurator is supported.
     *
     * @param string $name The name of the FieldSet configurator
     */
    public function hasConfigurator(string $name): bool
    {
        if (isset($this->configurators[$name]) || isset($this->serviceIds[$name])) {
            return true;
        }

        return class_exists($name) && \in_array(FieldSetConfigurator::class, class_implements($name), true);
    }
}
