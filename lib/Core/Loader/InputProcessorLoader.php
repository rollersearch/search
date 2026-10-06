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

namespace Rollerworks\Component\Search\Loader;

use Psr\Container\ContainerInterface;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Input\JsonInput;
use Rollerworks\Component\Search\Input\NormStringQueryInput;
use Rollerworks\Component\Search\Input\StringQueryInput;
use Rollerworks\Component\Search\Input\Validator;
use Rollerworks\Component\Search\InputProcessor;

/**
 * InputProcessorLoader provides lazy loading of Input processors.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class InputProcessorLoader
{
    /**
     * @param ContainerInterface    $container  A PSR-11 compatible Service container
     * @param array<string, string> $serviceIds Format alias to service-id mapping,
     *                                          eg. 'json' => 'ServiceId of the container'
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $serviceIds,
    ) {}

    /**
     * Create a new InputProcessorLoader with the build-in InputProcessors loadable.
     */
    public static function create(?Validator $validator = null): self
    {
        return new self(
            new ClosureContainer(
                [
                    'rollerworks_search.input.json' => static fn (): JsonInput => new JsonInput($validator),
                    'rollerworks_search.input.string_query' => static fn (): StringQueryInput => new StringQueryInput($validator),
                    'rollerworks_search.input.norm_string_query' => static fn (): NormStringQueryInput => new NormStringQueryInput($validator),
                ]
            ),
            [
                'json' => 'rollerworks_search.input.json',
                'string_query' => 'rollerworks_search.input.string_query',
                'norm_string_query' => 'rollerworks_search.input.norm_string_query',
            ]
        );
    }

    /**
     * @throws \InvalidArgumentException when there is no input processor with the given name
     */
    public function get(string $name): InputProcessor
    {
        if (! isset($this->serviceIds[$name])) {
            throw new InvalidArgumentException(
                \sprintf('Enable to load input-processor, "%s" is not registered as processor.', $name)
            );
        }

        return $this->container->get($this->serviceIds[$name]);
    }
}
