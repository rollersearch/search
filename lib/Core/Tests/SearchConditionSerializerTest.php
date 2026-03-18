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
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\GenericFieldSet;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchConditionSerializer;
use Rollerworks\Component\Search\SearchFactory;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
final class SearchConditionSerializerTest extends TestCase
{
    private SearchConditionSerializer $serializer;
    private GenericFieldSet $fieldSet;

    protected function setUp(): void
    {
        $field = $this->createMock(FieldConfig::class);
        $field->method('getName')->willReturn('id');

        $this->fieldSet = new GenericFieldSet(['id' => $field], 'foobar');

        $factory = $this->createMock(SearchFactory::class);
        $factory
            ->method('createFieldSet')
            ->with('foobar')
            ->willReturn($this->fieldSet)
        ;

        $this->serializer = new SearchConditionSerializer($factory);
    }

    /**
     * @test
     */
    public function serialize_unserialize_legacy_format(): void
    {
        $date = new \DateTimeImmutable();

        $fieldId = new ValuesBag();
        $fieldId->addSimpleValue(10);
        $fieldId->addSimpleValue($date);

        $valuesGroup0 = new ValuesGroup();
        $valuesGroup0->addField('id', $fieldId);

        $fieldName = new ValuesBag();
        $fieldName->addSimpleValue(10);
        $fieldName->addSimpleValue($date);

        $valuesGroup1 = new ValuesGroup();

        $valuesGroup1->addField('name', $fieldName);
        $valuesGroup0->addGroup($valuesGroup1);

        $searchCondition = new SearchCondition($this->fieldSet, $valuesGroup0);
        $searchCondition->setAttribute('original_input', 'id: 5;');

        $serialized = $this->serializer->serialize($searchCondition);
        unset($serialized[2]); // Remove attributes (old format)

        $serialized = serialize($serialized);
        $unSerialized = $this->serializer->unserialize(unserialize($serialized));
        $searchCondition->removeAttribute('original_input');

        self::assertEquals($searchCondition, $unSerialized);
    }

    /**
     * @test
     */
    public function serialize_unserialize(): void
    {
        $date = new \DateTimeImmutable();

        $fieldId = new ValuesBag();
        $fieldId->addSimpleValue(10);
        $fieldId->addSimpleValue($date);

        $valuesGroup0 = new ValuesGroup();
        $valuesGroup0->addField('id', $fieldId);

        $fieldName = new ValuesBag();
        $fieldName->addSimpleValue(10);
        $fieldName->addSimpleValue($date);

        $valuesGroup1 = new ValuesGroup();

        $valuesGroup1->addField('name', $fieldName);
        $valuesGroup0->addGroup($valuesGroup1);

        $searchCondition = new SearchCondition($this->fieldSet, $valuesGroup0);
        $searchCondition->setAttribute('original_input', 'id: 5;');

        $serialized = serialize($this->serializer->serialize($searchCondition));
        $unSerialized = $this->serializer->unserialize(unserialize($serialized));

        self::assertEquals($searchCondition, $unSerialized);
    }

    /**
     * @test
     */
    public function unserialize_missing_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serialized search condition must be a numeric-array with at least keys [0, 1].');

        $this->serializer->unserialize(['foobar']);
    }

    /**
     * @test
     */
    public function unserialize_missing_fields2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serialized search condition must be a numeric-array with at least keys [0, 1].');

        $this->serializer->unserialize([1 => 'foobar', 2 => 'wrong']);
    }

    /**
     * @test
     */
    public function unserialize_wrong_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serialized search condition must be a numeric-array with at least keys [0, 1].');

        $this->serializer->unserialize(['foobar', 'foo' => 'bar']);
    }

    /**
     * @test
     */
    public function unserialize_invalid_data(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to unserialize invalid value.');

        $this->serializer->unserialize(['foobar', '{i-am-invalid}']);
    }

    /**
     * @test
     */
    public function unserialize_invalid_attributes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Serialized search condition index "2" is expected to be an "array" got "string".');

        $this->serializer->unserialize(['foobar', '{i-am-invalid}', 'attributes']);
    }
}
