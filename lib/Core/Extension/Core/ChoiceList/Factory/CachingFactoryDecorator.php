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

namespace Rollerworks\Component\Search\Extension\Core\ChoiceList\Factory;

use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\Loader\ChoiceLoader;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceListView;

/**
 * Caches the choice lists created by the decorated factory.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class CachingFactoryDecorator implements ChoiceListFactory
{
    /** @var ChoiceList[] */
    private array $lists = [];

    /** @var ChoiceListView[] */
    private array $views = [];

    public function __construct(
        private readonly ChoiceListFactory $decoratedFactory,
    ) {}

    public function getDecoratedFactory(): ChoiceListFactory
    {
        return $this->decoratedFactory;
    }

    public function createListFromChoices($choices, $value = null): ChoiceList
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }

        // The value is not validated on purpose. The decorated factory may
        // decide which values to accept and which not.

        // We ignore the choice groups for caching. If two choice lists are
        // requested with the same choices, but a different grouping, the same
        // choice list is returned.
        self::flatten($choices, $flatChoices);

        $hash = self::generateHash([$flatChoices, $value], 'fromChoices');

        if (! isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromChoices($choices, $value);
        }

        return $this->lists[$hash];
    }

    public function createListFromLoader(ChoiceLoader $loader, $value = null): ChoiceList
    {
        $hash = self::generateHash([$loader, $value], 'fromLoader');

        if (! isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromLoader($loader, $value);
        }

        return $this->lists[$hash];
    }

    public function createView(ChoiceList $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null): ChoiceListView
    {
        // The input is not validated on purpose. This way, the decorated
        // factory may decide which input to accept and which not.
        $hash = self::generateHash([$list, $preferredChoices, $label, $index, $groupBy, $attr]);

        if (! isset($this->views[$hash])) {
            $this->views[$hash] = $this->decoratedFactory->createView(
                $list,
                $preferredChoices,
                $label,
                $index,
                $groupBy,
                $attr
            );
        }

        return $this->views[$hash];
    }

    /**
     * Generates a SHA-256 hash for the given value.
     *
     * Optionally, a namespace string can be passed. Calling this method will produce
     * the same values, but different namespaces, will return different hashes.
     *
     * @param mixed  $value     The value to hash
     * @param string $namespace Optional. The namespace
     *
     * @return string The SHA-256 hash
     */
    private static function generateHash($value, string $namespace = ''): string
    {
        if (\is_object($value)) {
            $value = spl_object_hash($value);
        } elseif (\is_array($value)) {
            array_walk_recursive($value, static function (&$v): void {
                if (\is_object($v)) {
                    $v = spl_object_hash($v);
                }
            });
        }

        return hash('sha256', $namespace . ':' . serialize($value));
    }

    /**
     * Flattens an array into the given output variable.
     *
     * @param array      $array  The array to flatten
     * @param array|null $output The flattened output
     */
    private static function flatten(array $array, &$output): void
    {
        if ($output === null) {
            $output = [];
        }

        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                self::flatten($value, $output);

                continue;
            }

            $output[$key] = $value;
        }
    }
}
