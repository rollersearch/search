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

namespace Rollerworks\Component\Search\Tests;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Exception\UnsupportedFieldSetException;
use Rollerworks\Component\Search\FieldSet;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\SearchPrimaryCondition;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class SearchConditionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_check_if_field_set_is_supported(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->assertFieldSetName('test');
        $condition->assertFieldSetName('test', 'foo');

        // Dummy tests, no error means it works.
        self::assertEquals(new ValuesGroup(), $condition->getValuesGroup());
    }

    /**
     * @test
     */
    public function it_gives_an_exception_when_checked_field_set_is_not_supported(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());

        $this->expectException(UnsupportedFieldSetException::class);
        $this->expectExceptionMessage((new UnsupportedFieldSetException(['bar', 'foo'], 'test'))->getMessage());

        $condition->assertFieldSetName('bar', 'foo');
    }

    /**
     * @test
     */
    public function it_allows_setting_a_primary_condition(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $primaryCondition = new SearchPrimaryCondition((new ValuesGroup())->addField('id', new ValuesBag()));

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->setPrimaryCondition($primaryCondition);

        self::assertEquals($primaryCondition, $condition->getPrimaryCondition());
    }

    /**
     * @test
     */
    public function it_allows_unsetting_the_primary_condition(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $primaryCondition = new SearchPrimaryCondition((new ValuesGroup())->addField('id', new ValuesBag()));

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->setPrimaryCondition($primaryCondition);
        $condition->setPrimaryCondition(null);

        self::assertNull($condition->getPrimaryCondition());
    }

    /**
     * @test
     */
    public function it_gives_whether_condition_is_empty(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        // Empty condition
        self::assertTrue((new SearchCondition($fieldSet, new ValuesGroup()))->isEmpty());

        // With primary condition (not part of the cached condition)
        self::assertTrue(
            (new SearchCondition($fieldSet, new ValuesGroup()))
                ->setPrimaryCondition(
                    new SearchPrimaryCondition(
                        (new ValuesGroup())->addField('id', new ValuesBag())
                    ),
                )->isEmpty(),
        );

        // -- None empty --

        // Field
        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->getValuesGroup()->addField('id', new ValuesBag());
        self::assertFalse($condition->isEmpty());

        // Nested group
        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->getValuesGroup()->addGroup(new ValuesGroup());
        self::assertFalse($condition->isEmpty());

        // Ordering
        self::assertFalse(
            (new SearchCondition($fieldSet, new ValuesGroup()))
                ->setOrder(new SearchOrder(['@id' => 'desc']))
                ->isEmpty(),
        );
    }

    /**
     * @test
     */
    public function it_allows_attributes_safe_values(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());
        $condition->setAttribute('test', null);
        $condition->setAttribute('foo', 'value');
        $condition->setAttribute('bar', true);
        $condition->setAttribute(FieldSet::class, false);
        $condition->setAttribute(SearchCondEnum::Hey, 'now');
        $condition->setAttribute('ignore', [null]);
        $condition->setAttribute('stdtest', [new \stdClass()]);
        $condition->setAttribute('.test', ['he', true, 10, false, 20.0, $d = new \DateTimeImmutable()]);
        $condition->setAttribute('deep', [[[[[[[[[[[[[[[[[[[['Hey']]]]]]]]]]]]]]]]]]]]); // Deep structures are accepted to a max of 20 levels

        self::assertEquals(
            [
                'test' => null,
                'foo' => 'value',
                'bar' => true,
                FieldSet::class => false,
                SearchCondEnum::class . '::Hey' => 'now',
                'ignore' => [null],
                'stdtest' => [new \stdClass()],
                '.test' => ['he', true, 10, false, 20.0, $d],
                'deep' => [[[[[[[[[[[[[[[[[[[['Hey']]]]]]]]]]]]]]]]]]]],
            ],
            $condition->getAttributes(),
        );
    }

    /**
     * @test
     *
     * @dataProvider provide_unsupported_attributes
     */
    public function it_throws_with_unsupported_attribute_value(mixed $value, string $message): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $condition->setAttribute('my-attr', $value);
    }

    /**
     * @test
     */
    public function it_throws_with_unsupported_attribute_value_with_enum_key(): void
    {
        $fieldSet = $this->createMock(FieldSet::class);
        $fieldSet->expects(self::any())->method('getSetName')->willReturn('test');

        $condition = new SearchCondition($fieldSet, new ValuesGroup());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Attribute "%s::Mea" value "%s object" for a SearchCondition could not be serialized. Message: This class is not serializable.', SearchCondEnum::class, NoneSerializableAttributeValue::class));

        $condition->setAttribute(SearchCondEnum::Mea, new NoneSerializableAttributeValue());
    }

    /**
     * @return iterable<array-key, array{0: mixed, 1: string}>
     */
    public static function provide_unsupported_attributes(): iterable
    {
        yield 'resource value' => [
            fopen('php://memory', 'r'),
            'Attribute "my-attr" value "resource (stream)" for a SearchCondition could not be serialized. Message: Resources are not accepted.',
        ];

        yield 'resource value at nesting level 1' => [
            [fopen('php://memory', 'r')],
            'Attribute "my-attr" value "resource (stream)" (at nesting level 1) for a SearchCondition could not be serialized. Message: Resources are not accepted.',
        ];

        yield 'resource value at nesting level 2' => [
            [false, [true, fopen('php://memory', 'r')]],
            'Attribute "my-attr" value "resource (stream)" (at nesting level 2) for a SearchCondition could not be serialized. Message: Resources are not accepted.',
        ];

        yield 'unsupported object' => [
            new NoneSerializableAttributeValue(),
            \sprintf('Attribute "my-attr" value "%s object" for a SearchCondition could not be serialized. Message: This class is not serializable.', NoneSerializableAttributeValue::class),
        ];

        yield 'value structure too deep' => [
            [[[[[[[[[[[[[[[[[[[[[['Hey']]]]]]]]]]]]]]]]]]]]]], // > 20 levels deep
            'Attribute "my-attr" value (at nested level 21) is too deeply nested to allow type checking.',
        ];
    }
}

/**
 * @internal
 */
final class NoneSerializableAttributeValue
{
    public function __serialize(): never
    {
        throw new \LogicException('This class is not serializable.');
    }
}

/**
 * @internal
 */
enum SearchCondEnum
{
    case Hey;

    case Mea;
}
