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

namespace Rollerworks\Component\Search\Tests\Input;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\DataTransformer;
use Rollerworks\Component\Search\Exception\GroupsNestingException;
use Rollerworks\Component\Search\Exception\GroupsOverflowException;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Exception\UnknownFieldException;
use Rollerworks\Component\Search\Exception\UnsupportedValueTypeException;
use Rollerworks\Component\Search\Exception\ValuesOverflowException;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\OrderTransformer;
use Rollerworks\Component\Search\Extension\Core\Type\DateType;
use Rollerworks\Component\Search\Extension\Core\Type\IntegerType;
use Rollerworks\Component\Search\Extension\Core\Type\TextType;
use Rollerworks\Component\Search\Field\AbstractFieldTypeExtension;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\OrderFieldType;
use Rollerworks\Component\Search\GenericFieldSetBuilder;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\Test\SearchIntegrationTestCase;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\ExcludedRange;
use Rollerworks\Component\Search\Value\PatternMatch;
use Rollerworks\Component\Search\Value\Range;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * @internal
 */
abstract class InputProcessorTestCase extends SearchIntegrationTestCase
{
    abstract protected function getProcessor(): InputProcessor;

    protected function getFieldSet(bool $build = true, bool $order = false)
    {
        $fieldSet = new GenericFieldSetBuilder($this->getFactory());
        $fieldSet->add('id', IntegerType::class);
        $fieldSet->add('_id', IntegerType::class);
        $fieldSet->add('_group', IntegerType::class);
        $fieldSet->add('name', TextType::class);
        $fieldSet->add('lastname', TextType::class);
        $fieldSet->add('date', DateType::class, ['pattern' => 'MM-dd-yyyy']);

        if ($order) {
            $fieldSet->add('@date', OrderFieldType::class, ['case' => OrderTransformer::CASE_LOWERCASE, 'alias' => ['up' => 'ASC', 'down' => 'DESC'], 'default' => 'desc']);
            $fieldSet->add('@id', OrderFieldType::class, ['default' => 'ASC']);
        }

        $fieldSet->set(
            $this->getFactory()->createField('no-range-field', IntegerType::class)
                ->setValueTypeSupport(Range::class, false)
        );

        $fieldSet->set(
            $this->getFactory()->createField('no-compares-field', IntegerType::class)->setValueTypeSupport(
                Compare::class,
                false
            )
        );

        $fieldSet->set(
            $this->getFactory()->createField('no-matchers-field', IntegerType::class)->setValueTypeSupport(
                PatternMatch::class,
                false
            )
        );

        return $build ? $fieldSet->getFieldSet() : $fieldSet;
    }

    /**
     * @test
     *
     * @dataProvider provideEmptyInputTests
     */
    public function it_processes_an_empty_input($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());

        $expectedGroup = new ValuesGroup();

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideEmptyInputTests(): iterable;

    /**
     * @test
     *
     * @dataProvider provideSingleValuePairTests
     */
    public function it_processes_values($input, array $order = []): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet(true, true));
        $config->setDefaultField('id');

        $expectedGroup = new ValuesGroup();

        if (! \in_array($input, ['', '[]'], true)) {
            $values = new ValuesBag();
            $values->addSimpleValue('value');
            $values->addSimpleValue('value2');
            $values->addSimpleValue('٤٤٤٦٥٤٦٠٠'); // number in Arab
            $values->addSimpleValue('30');
            $values->addSimpleValue('30L');
            $values->addExcludedSimpleValue('value3');
            $expectedGroup->addField('name', $values);
        }

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);

        if ($order) {
            $expectedOrderGroup = new ValuesGroup();

            foreach ($order as $name => $direction) {
                $orderValueBag = new ValuesBag();
                $orderValueBag->addSimpleValue($direction);
                $expectedOrderGroup->addField($name, $orderValueBag);
            }
            $condition->setOrder(new SearchOrder($expectedOrderGroup));
        }

        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideSingleValuePairTests();

    /**
     * @test
     *
     * @dataProvider provideMultipleValues
     */
    public function it_processes_multiple_fields($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('name');

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->addSimpleValue('value');
        $values->addSimpleValue('value2');
        $expectedGroup->addField('name', $values);

        $date = new \DateTimeImmutable('2014-12-16 00:00:00 UTC');

        $values = new ValuesBag();
        $values->addSimpleValue($date);
        $expectedGroup->addField('date', $values);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideMultipleValues();

    /**
     * @dataProvider provideRangeValues
     *
     * @test
     */
    public function it_processes_range_values($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('id');

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->add(new Range(1, 10));
        $values->add(new Range(15, 30));
        $values->add(new Range(100, 200, false));
        $values->add(new Range(310, 400, true, false));
        $values->add(new ExcludedRange(50, 70));
        $expectedGroup->addField('id', $values);

        $date = new \DateTimeImmutable('2014-12-16 00:00:00 UTC');
        $date2 = new \DateTimeImmutable('2014-12-20 00:00:00 UTC');

        $values = new ValuesBag();
        $values->add(new Range($date, $date2, true, true));
        $expectedGroup->addField('date', $values);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideRangeValues();

    /**
     * @dataProvider provideComparisonValues
     *
     * @test
     */
    public function it_processes_comparisons($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('id');

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->add(new Compare(1, '>'));
        $values->add(new Compare(2, '<'));
        $values->add(new Compare(5, '<='));
        $values->add(new Compare(8, '>='));
        $values->add(new Compare(20, '<>'));
        $expectedGroup->addField('id', $values);

        $date = new \DateTimeImmutable('2014-12-16 00:00:00 UTC');

        $values = new ValuesBag();
        $values->add(new Compare($date, '>='));
        $expectedGroup->addField('date', $values);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideComparisonValues();

    /**
     * @test
     *
     * @dataProvider provideMatcherValues
     */
    public function it_processes_matchers($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->add(new PatternMatch('value', PatternMatch::PATTERN_CONTAINS));
        $values->add(new PatternMatch('value2', PatternMatch::PATTERN_STARTS_WITH, true));
        $values->add(new PatternMatch('value3', PatternMatch::PATTERN_ENDS_WITH));
        $values->add(new PatternMatch('value4', PatternMatch::PATTERN_NOT_CONTAINS));
        $values->add(new PatternMatch('value5', PatternMatch::PATTERN_NOT_CONTAINS, true));
        $values->add(new PatternMatch('value9', PatternMatch::PATTERN_EQUALS));
        $values->add(new PatternMatch('value10', PatternMatch::PATTERN_NOT_EQUALS));
        $values->add(new PatternMatch('value11', PatternMatch::PATTERN_EQUALS, true));
        $values->add(new PatternMatch('value12', PatternMatch::PATTERN_NOT_EQUALS, true));
        $expectedGroup->addField('name', $values);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideMatcherValues();

    /**
     * @test
     *
     * @dataProvider provideGroupTests
     */
    public function it_processes_groups($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('name');

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->addSimpleValue('value');
        $values->addSimpleValue('value2');
        $expectedGroup->addField('name', $values);

        $values = new ValuesBag();
        $values->addSimpleValue('value3');
        $values->addSimpleValue('value4');

        $subGroup = new ValuesGroup();
        $subGroup->addField('name', $values);
        $expectedGroup->addGroup($subGroup);

        $values = new ValuesBag();
        $values->addSimpleValue('value8');
        $values->addSimpleValue('value10');

        $subGroup = new ValuesGroup(ValuesGroup::GROUP_LOGICAL_OR);
        $subGroup->addField('name', $values);
        $expectedGroup->addGroup($subGroup);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideGroupTests();

    /**
     * @test
     *
     * @dataProvider provideRootLogicalTests
     */
    public function it_processes_root_logical($input, string $logical = ValuesGroup::GROUP_LOGICAL_AND): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('name');

        $expectedGroup = new ValuesGroup($logical);

        $values = new ValuesBag();
        $values->addSimpleValue('value');
        $values->addSimpleValue('value2');
        $expectedGroup->addField('name', $values);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideRootLogicalTests();

    /**
     * @test
     *
     * @dataProvider provideMultipleSubGroupTests
     */
    public function it_processes_multiple_subgroups($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('name');

        $expectedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->addSimpleValue('value');
        $values->addSimpleValue('value2');

        $subGroup = new ValuesGroup();
        $subGroup->addField('name', $values);

        $values = new ValuesBag();
        $values->addSimpleValue('value3');
        $values->addSimpleValue('value4');
        $expectedGroup->addGroup($subGroup);

        $subGroup2 = new ValuesGroup();
        $subGroup2->addField('name', $values);
        $expectedGroup->addGroup($subGroup2);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideMultipleSubGroupTests();

    /**
     * @test
     *
     * @dataProvider provideNestedGroupTests
     */
    public function it_processes_nested_subgroups($input): void
    {
        $processor = $this->getProcessor();
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setDefaultField('name');

        $expectedGroup = new ValuesGroup();
        $nestedGroup = new ValuesGroup();

        $values = new ValuesBag();
        $values->addSimpleValue('value');
        $values->addSimpleValue('value2');
        $nestedGroup->addField('name', $values);

        $subGroup = new ValuesGroup();
        $subGroup->addGroup($nestedGroup);
        $expectedGroup->addGroup($subGroup);

        $condition = new SearchCondition($config->getFieldSet(), $expectedGroup);
        $this->assertConditionEquals($input, $condition, $processor, $config);
    }

    /**
     * @return array[]
     */
    abstract public static function provideNestedGroupTests();

    /**
     * @test
     *
     * @dataProvider provideValueOverflowTests
     */
    public function it_errors_when_maximum_values_count_is_exceeded($input, string $fieldName, string $path): void
    {
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setMaxValues(3);

        $e = new ValuesOverflowException($fieldName, 3, $path);
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrors($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function provideValueOverflowTests();

    /**
     * @test
     *
     * @dataProvider provideGroupsOverflowTests
     */
    public function it_errors_when_maximum_groups_count_is_exceeded($input, string $path): void
    {
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setMaxGroups(3);

        $e = new GroupsOverflowException(3, $path);
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrors($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function provideGroupsOverflowTests();

    /**
     * This tests ensures a private field will throw an UnknownFieldException.
     *
     * @test
     *
     * @dataProvider providePrivateFieldTests
     */
    public function it_errors_when_private_field_was_used($input, string $fieldName): void
    {
        $config = new ProcessorConfig($this->getFieldSet());

        $e = new UnknownFieldException($fieldName);
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrorsWithoutCause($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function providePrivateFieldTests();

    /**
     * @test
     *
     * @dataProvider provideNestingLevelExceededTests
     */
    public function it_errors_when_maximum_nesting_level_is_reached($input, string $path): void
    {
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setMaxNestingLevel(1);

        $e = new GroupsNestingException(1, $path);
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrors($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function provideNestingLevelExceededTests();

    /**
     * @test
     *
     * @dataProvider provideUnknownFieldTests
     */
    public function it_errors_when_the_field_does_not_exist_in_fieldset($input): void
    {
        $config = new ProcessorConfig($this->getFieldSet());

        $e = new UnknownFieldException('field2');
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrors($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function provideUnknownFieldTests();

    /**
     * @test
     *
     * @dataProvider provideUnsupportedValueTypeExceptionTests
     */
    public function it_errors_when_the_field_does_not_support_the_value_type($input, string $fieldName, string $valueType): void
    {
        $config = new ProcessorConfig($this->getFieldSet());

        $e = new UnsupportedValueTypeException($fieldName, $valueType);
        $error = $e->toErrorMessageObj();

        $this->assertConditionContainsErrors($input, $config, [$error]);
    }

    /**
     * @return array[]
     */
    abstract public static function provideUnsupportedValueTypeExceptionTests();

    /**
     * @test
     *
     * @dataProvider provideInvalidRangeTests
     */
    public function it_errors_when_a_range_has_invalid_bounds($input, array $path): void
    {
        $config = new ProcessorConfig($this->getFieldSet());

        $errors = [
            ConditionErrorMessage::withMessageTemplate($path[0], 'The lower range value {{ lower }} should be lower than the upper range value {{ upper }}.', ['{{ lower }}' => '30', '{{ upper }}' => '10']),
            ConditionErrorMessage::withMessageTemplate($path[1], 'The lower range value {{ lower }} should be lower than the upper range value {{ upper }}.', ['{{ lower }}' => '40', '{{ upper }}' => '20']),
        ];

        $this->assertConditionContainsErrorsWithoutCause($input, $config, $errors);
    }

    /**
     * @return array[]
     */
    abstract public static function provideInvalidRangeTests();

    /**
     * @param ConditionErrorMessage[] $errors
     *
     * @test
     *
     * @dataProvider provideInvalidValueTests
     */
    public function it_errors_when_transformation_fails($input, array $errors): void
    {
        $config = new ProcessorConfig($this->getFieldSet());

        $this->assertConditionContainsErrorsWithoutCause($input, $config, $errors);
    }

    /**
     * @return array[]
     */
    abstract public static function provideInvalidValueTests();

    /**
     * @param ConditionErrorMessage[] $errors
     *
     * @test
     *
     * @dataProvider provideInvalidWithMessageValueTests
     */
    public function it_errors_with_invalid_message_when_transformation_fails($input, array $errors): void
    {
        $alwaysFailTransformer = new class implements DataTransformer {
            public function transform($value): void
            {
                // No-op
            }

            public function reverseTransform($value): never
            {
                throw new TransformationFailedException('Error.', 0, null, 'I explicitly refuse the accept this value.', ['value' => $value]);
            }
        };

        $this->factoryBuilder->addTypeExtensions([
            new class($alwaysFailTransformer) extends AbstractFieldTypeExtension {
                public function __construct(
                    private readonly DataTransformer $transformer,
                ) {}

                public function buildType(FieldConfig $builder, array $options): void
                {
                    $builder->setNormTransformer($this->transformer);
                    $builder->setViewTransformer($this->transformer);
                }

                public function getExtendedType(): string
                {
                    return TextType::class;
                }
            },
        ]);

        $fieldSetBuilder = new GenericFieldSetBuilder($this->getFactory());
        $fieldSetBuilder->add('name', TextType::class);
        $fieldSet = $fieldSetBuilder->getFieldSet();

        $config = new ProcessorConfig($fieldSet);

        $this->assertConditionContainsErrorsWithoutCause($input, $config, $errors);
    }

    /**
     * @return array[]
     */
    abstract public static function provideInvalidWithMessageValueTests();

    /**
     * @param ConditionErrorMessage[] $errors
     *
     * @test
     *
     * @dataProvider provideNestedErrorsTests
     */
    public function it_checks_nested_fields($input, array $errors): void
    {
        $config = new ProcessorConfig($this->getFieldSet());
        $config->setMaxNestingLevel(10);

        $this->assertConditionContainsErrorsWithoutCause($input, $config, $errors);
    }

    /**
     * @return array[]
     */
    abstract public static function provideNestedErrorsTests();
}
