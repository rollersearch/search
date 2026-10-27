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

namespace Rollerworks\Component\Search\Tests\Extension\Core\Type;

use Rollerworks\Component\Search\Extension\Core\Type\NumberType;
use Rollerworks\Component\Search\FieldSetView;
use Rollerworks\Component\Search\Test\FieldTransformationAssertion;
use Rollerworks\Component\Search\Test\SearchIntegrationTestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @internal
 */
final class NumberTypeTest extends SearchIntegrationTestCase
{
    /**
     * @test
     */
    public function casts_to_integer(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('1,678', '1.678')
            ->successfullyTransformsTo('1.678')
            ->andReverseTransformsTo('1,678', '1.678')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('1', '1')
            ->successfullyTransformsTo('1')
            ->andReverseTransformsTo('1', '1')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('01', '01')
            ->successfullyTransformsTo('1')
            ->andReverseTransformsTo('1', '1')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('-1')
            ->successfullyTransformsTo('-1')
            ->andReverseTransformsTo('-1')
        ;
    }

    /**
     * @test
     */
    public function wrong_input_fails(): void
    {
        $field = $this->getFactory()->createField('integer', NumberType::class);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('foo')
            ->failsToTransforms()
        ;
    }

    /**
     * @test
     */
    public function default_formatting(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,67890', '12345.67890')
            ->successfullyTransformsTo('12345.67890')
            ->andReverseTransformsTo('12345,679', '12345.67890')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,679', '12345.679')
            ->successfullyTransformsTo('12345.679')
            ->andReverseTransformsTo('12345,679', '12345.679')
        ;
    }

    /**
     * @test
     */
    public function non_western_formatting(): void
    {
        \Locale::setDefault('ar_BH');

        $field = $this->getFactory()->createField('number', NumberType::class);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('١٢٣٤٥٫٦٧٨٩٠', '12345.67890')
            ->successfullyTransformsTo('12345.6789')
            ->andReverseTransformsTo('١٢٣٤٥٫٦٧٩', '12345.6789')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('١٢٣٤٥٫٦٧٩', '12345.679')
            ->successfullyTransformsTo('12345.679')
            ->andReverseTransformsTo('١٢٣٤٥٫٦٧٩', '12345.679')
        ;

        // Ensure local option is honored
        FieldTransformationAssertion::assertThat($this->getFactory()->createField('number', NumberType::class, ['locale' => 'en']))
            ->withInput('1.678', '1.678')
            ->successfullyTransformsTo('1.678')
            ->andReverseTransformsTo('1.678', '1.678')
        ;
    }

    /**
     * @test
     */
    public function default_formatting_with_grouping(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class, ['grouping' => true]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12.345,679', '12345.679')
            ->successfullyTransformsTo('12345.679')
            ->andReverseTransformsTo('12.345,679', '12345.679')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,679', '12345.679')
            ->successfullyTransformsTo('12345.679')
            ->andReverseTransformsTo('12.345,679', '12345.679')
        ;
    }

    /**
     * @test
     */
    public function default_formatting_with_precision(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class, ['scale' => 2]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,67890', '12345.67890')
            ->successfullyTransformsTo('12345.68')
            ->andReverseTransformsTo('12345,68', '12345.68')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,67', '12345.67')
            ->successfullyTransformsTo('12345.67')
            ->andReverseTransformsTo('12345,67', '12345.67')
        ;
    }

    /**
     * @test
     */
    public function default_formatting_with_rounding(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class, [
            'scale' => 0, 'rounding_mode' => \NumberFormatter::ROUND_UP,
        ]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345,54321', '12345.54321')
            ->successfullyTransformsTo('12346')
            ->andReverseTransformsTo('12346')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12345')
            ->successfullyTransformsTo('12345')
            ->andReverseTransformsTo('12345')
        ;
    }

    /**
     * @test
     */
    public function view_is_configured_properly(): void
    {
        $field = $this->getFactory()->createField('number', NumberType::class, [
            'scale' => 0, 'grouping' => false,
        ]);

        $field->finalizeConfig();
        $fieldView = $field->createView(new FieldSetView());

        self::assertArrayHasKey('scale', $fieldView->vars);
        self::assertArrayHasKey('grouping', $fieldView->vars);

        self::assertEquals(0, $fieldView->vars['scale']);
        self::assertFalse($fieldView->vars['grouping']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this, '58.1');

        \Locale::setDefault('de_DE');
    }
}
