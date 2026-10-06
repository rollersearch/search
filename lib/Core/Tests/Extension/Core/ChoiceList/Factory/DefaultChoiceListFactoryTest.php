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

namespace Rollerworks\Component\Search\Tests\Extension\Core\ChoiceList\Factory;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ArrayChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\Factory\DefaultChoiceListFactory;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\LazyChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\Loader\ChoiceLoader;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceGroupView;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceListView;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceView;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
final class DefaultChoiceListFactoryTest extends TestCase
{
    private object $obj1;
    private object $obj2;
    private object $obj3;
    private object $obj4;
    private ArrayChoiceList $list;
    private DefaultChoiceListFactory $factory;

    public function getValue(object $object): mixed
    {
        return $object->value;
    }

    public function getScalarValue(string $choice): string
    {
        switch ($choice) {
            case 'a':
                return 'a';

            case 'b':
                return 'b';

            case 'c':
                return '1';

            case 'd':
                return '2';

            default:
                throw new \InvalidArgumentException(\sprintf('Unexpected choice "%s".', $choice));
        }
    }

    public function getLabel(object $object): string
    {
        return $object->label;
    }

    public function getFormIndex(object $object)
    {
        return $object->index;
    }

    public function isPreferred(object $object): bool
    {
        return $this->obj2 === $object || $this->obj3 === $object;
    }

    public function getAttr(object $object)
    {
        return $object->attr;
    }

    public function getGroup(object $object): string
    {
        return $this->obj1 === $object || $this->obj2 === $object ? 'Group 1' : 'Group 2';
    }

    public function getGroupAsObject(object $object): DefaultChoiceListFactoryTest_Castable
    {
        return $this->obj1 === $object || $this->obj2 === $object
            ? new DefaultChoiceListFactoryTest_Castable('Group 1')
            : new DefaultChoiceListFactoryTest_Castable('Group 2');
    }

    protected function setUp(): void
    {
        $this->obj1 = (object) [
            'label' => 'A',
            'index' => 'w',
            'value' => 'a',
            'preferred' => false,
            'group' => 'Group 1',
            'attr' => [],
        ];

        $this->obj2 = (object) [
            'label' => 'B',
            'index' => 'x',
            'value' => 'b',
            'preferred' => true,
            'group' => 'Group 1',
            'attr' => ['attr1' => 'value1'],
        ];

        $this->obj3 = (object) [
            'label' => 'C',
            'index' => 'y',
            'value' => 1,
            'preferred' => true,
            'group' => 'Group 2',
            'attr' => ['attr2' => 'value2'],
        ];

        $this->obj4 = (object) [
            'label' => 'D',
            'index' => 'z',
            'value' => 2,
            'preferred' => false,
            'group' => 'Group 2',
            'attr' => [],
        ];

        $this->factory = new DefaultChoiceListFactory();
        $this->list = new ArrayChoiceList(
            [
                'A' => $this->obj1,
                'B' => $this->obj2,
                'C' => $this->obj3,
                'D' => $this->obj4,
            ]
        );
    }

    /**
     * @test
     */
    public function create_from_choices_empty(): void
    {
        $list = $this->factory->createListFromChoices([]);

        self::assertSame([], $list->getChoices());
        self::assertSame([], $list->getValues());
    }

    /**
     * @test
     */
    public function create_from_choices_flat(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4]
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_flat_traversable(): void
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator(['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4])
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_flat_values_as_callable(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4],
            $this->getValue(...)
        );

        $this->assertObjectListWithCustomValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_flat_values_as_closure(): void
    {
        $list = $this->factory->createListFromChoices(
            ['A' => $this->obj1, 'B' => $this->obj2, 'C' => $this->obj3, 'D' => $this->obj4],
            static fn ($object) => $object->value
        );

        $this->assertObjectListWithCustomValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_grouped(): void
    {
        $list = $this->factory->createListFromChoices(
            [
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ]
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_grouped_traversable(): void
    {
        $list = $this->factory->createListFromChoices(
            new \ArrayIterator([
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ])
        );

        $this->assertObjectListWithGeneratedValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_grouped_values_as_callable(): void
    {
        $list = $this->factory->createListFromChoices(
            [
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ],
            $this->getValue(...)
        );

        $this->assertObjectListWithCustomValues($list);
    }

    /**
     * @test
     */
    public function create_from_choices_grouped_values_as_closure(): void
    {
        $list = $this->factory->createListFromChoices(
            [
                'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
                'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            ],
            static fn ($object) => $object->value
        );

        $this->assertObjectListWithCustomValues($list);
    }

    /**
     * @test
     */
    public function create_from_loader(): void
    {
        $loader = $this->createMock(ChoiceLoader::class);

        $list = $this->factory->createListFromLoader($loader);

        self::assertEquals(new LazyChoiceList($loader), $list);
    }

    /**
     * @test
     */
    public function create_from_loader_with_values(): void
    {
        $loader = $this->createMock(ChoiceLoader::class);

        $value = static function (): void {};
        $list = $this->factory->createListFromLoader($loader, $value);

        self::assertEquals(new LazyChoiceList($loader, $value), $list);
    }

    /**
     * @test
     */
    public function create_view_flat(): void
    {
        $view = $this->factory->createView($this->list);

        self::assertEquals(
            new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ],
                []
            ),
            $view
        );
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3]
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices_empty_array(): void
    {
        $view = $this->factory->createView(
            $this->list,
            []
        );

        self::assertEquals(
            new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ],
                []
            ),
            $view
        );
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices_as_callable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            $this->isPreferred(...)
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices_as_closure(): void
    {
        $obj2 = $this->obj2;
        $obj3 = $this->obj3;

        $view = $this->factory->createView(
            $this->list,
            static fn ($object): bool => $obj2 === $object || $obj3 === $object
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices_closure_receives_key(): void
    {
        $view = $this->factory->createView(
            $this->list,
            static fn ($object, $key): bool => $key === 'B' || $key === 'C'
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_preferred_choices_closure_receives_value(): void
    {
        $view = $this->factory->createView(
            $this->list,
            static fn ($object, $key, $value): bool => $value === '1' || $value === '2'
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_label_as_callable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            $this->getLabel(...)
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_label_as_closure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static fn ($object) => $object->label
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_label_closure_receives_key(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static fn ($object, $key) => $key
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_label_closure_receives_value(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            static function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'A';

                    case '1': return 'B';

                    case '2': return 'C';

                    case '3': return 'D';
                }
            }
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_index_as_callable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            $this->getFormIndex(...)
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    /**
     * @test
     */
    public function create_view_flat_index_as_closure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static fn ($object) => $object->index
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    /**
     * @test
     */
    public function create_view_flat_index_closure_receives_key(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static function ($object, $key) {
                switch ($key) {
                    case 'A': return 'w';

                    case 'B': return 'x';

                    case 'C': return 'y';

                    case 'D': return 'z';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    /**
     * @test
     */
    public function create_view_flat_index_closure_receives_value(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            static function ($object, $key, $value) {
                switch ($value) {
                    case '0': return 'w';

                    case '1': return 'x';

                    case '2': return 'y';

                    case '3': return 'z';
                }
            }
        );

        $this->assertFlatViewWithCustomIndices($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_original_structure(): void
    {
        $list = new ArrayChoiceList([
            'Group 1' => ['A' => $this->obj1, 'B' => $this->obj2],
            'Group 2' => ['C' => $this->obj3, 'D' => $this->obj4],
            'Group empty' => [],
        ]);

        $view = $this->factory->createView(
            $list,
            [$this->obj2, $this->obj3]
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_empty(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null // ignored
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_as_callable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            $this->getGroup(...)
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_object_that_can_be_cast_to_string(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            $this->getGroupAsObject(...)
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_as_closure(): void
    {
        $obj1 = $this->obj1;
        $obj2 = $this->obj2;

        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn ($object): string => $obj1 === $object || $obj2 === $object ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_closure_receives_key(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn ($object, $key): string => $key === 'A' || $key === 'B' ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_group_by_closure_receives_value(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            static fn ($object, $key, $value): string => $value === '0' || $value === '1' ? 'Group 1' : 'Group 2'
        );

        $this->assertGroupedView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_as_array(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            [
                'B' => ['attr1' => 'value1'],
                'C' => ['attr2' => 'value2'],
            ]
        );

        $this->assertFlatViewWithAttr($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_empty(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            []
        );

        $this->assertFlatView($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_as_callable(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            $this->getAttr(...)
        );

        $this->assertFlatViewWithAttr($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_as_closure(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static fn ($object) => $object->attr
        );

        $this->assertFlatViewWithAttr($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_closure_receives_key(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static fn ($object, $key): array => match ($key) {
                'B' => ['attr1' => 'value1'],
                'C' => ['attr2' => 'value2'],
                default => [],
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    /**
     * @test
     */
    public function create_view_flat_attr_closure_receives_value(): void
    {
        $view = $this->factory->createView(
            $this->list,
            [$this->obj2, $this->obj3],
            null, // label
            null, // index
            null, // group
            static fn ($object, $key, $value): array => match ($value) {
                '1' => ['attr1' => 'value1'],
                '2' => ['attr2' => 'value2'],
                default => [],
            }
        );

        $this->assertFlatViewWithAttr($view);
    }

    private function assertObjectListWithGeneratedValues(ChoiceList $list): void
    {
        self::assertSame(['0', '1', '2', '3'], $list->getValues());

        self::assertSame([
            0 => $this->obj1,
            1 => $this->obj2,
            2 => $this->obj3,
            3 => $this->obj4,
        ], $list->getChoices());

        self::assertSame([
            0 => 'A',
            1 => 'B',
            2 => 'C',
            3 => 'D',
        ], $list->getOriginalKeys());
    }

    private function assertScalarListWithCustomValues(ChoiceList $list): void
    {
        self::assertSame(['a', 'b', '1', '2'], $list->getValues());

        self::assertSame(
            [
                'a' => 'a',
                'b' => 'b',
                1 => 'c',
                2 => 'd',
            ],
            $list->getChoices()
        );

        self::assertSame(
            [
                'a' => 'A',
                'b' => 'B',
                1 => 'C',
                2 => 'D',
            ],
            $list->getOriginalKeys()
        );
    }

    private function assertObjectListWithCustomValues(ChoiceList $list): void
    {
        self::assertSame(['a', 'b', '1', '2'], $list->getValues());

        self::assertSame(
            [
                'a' => $this->obj1,
                'b' => $this->obj2,
                1 => $this->obj3,
                2 => $this->obj4,
            ],
            $list->getChoices()
        );

        self::assertSame(
            [
                'a' => 'A',
                'b' => 'B',
                1 => 'C',
                2 => 'D',
            ],
            $list->getOriginalKeys()
        );
    }

    private function assertFlatView($view): void
    {
        self::assertEquals(
            new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ],
                [
                    1 => new ChoiceView($this->obj2, '1', 'B'),
                    2 => new ChoiceView($this->obj3, '2', 'C'),
                ]
            ),
            $view
        );
    }

    private function assertFlatViewWithCustomIndices($view): void
    {
        self::assertEquals(
            new ChoiceListView(
                [
                    'w' => new ChoiceView($this->obj1, '0', 'A'),
                    'z' => new ChoiceView($this->obj4, '3', 'D'),
                ],
                [
                    'x' => new ChoiceView($this->obj2, '1', 'B'),
                    'y' => new ChoiceView($this->obj3, '2', 'C'),
                ]
            ),
            $view
        );
    }

    private function assertFlatViewWithAttr($view): void
    {
        self::assertEquals(
            new ChoiceListView(
                [
                    0 => new ChoiceView($this->obj1, '0', 'A'),
                    3 => new ChoiceView($this->obj4, '3', 'D'),
                ],
                [
                    1 => new ChoiceView(
                        $this->obj2,
                        '1',
                        'B',
                        ['attr1' => 'value1']
                    ),
                    2 => new ChoiceView(
                        $this->obj3,
                        '2',
                        'C',
                        ['attr2' => 'value2']
                    ),
                ]
            ),
            $view
        );
    }

    private function assertGroupedView($view): void
    {
        self::assertEquals(
            new ChoiceListView(
                [
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        [0 => new ChoiceView($this->obj1, '0', 'A')]
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        [3 => new ChoiceView($this->obj4, '3', 'D')]
                    ),
                ],
                [
                    'Group 1' => new ChoiceGroupView(
                        'Group 1',
                        [1 => new ChoiceView($this->obj2, '1', 'B')]
                    ),
                    'Group 2' => new ChoiceGroupView(
                        'Group 2',
                        [2 => new ChoiceView($this->obj3, '2', 'C')]
                    ),
                ]
            ),
            $view
        );
    }
}

/**
 * @ignore
 */
final class DefaultChoiceListFactoryTest_Castable implements \Stringable
{
    public function __construct(
        private mixed $property,
    ) {}

    public function __toString(): string
    {
        return (string) $this->property;
    }
}
