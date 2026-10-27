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

namespace Rollerworks\Component\Search\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @internal
 */
final class NumberToLocalizedStringTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Locale::setDefault('en');
    }

    /**
     * @dataProvider provideTransformations
     *
     * @test
     */
    public function transform(float | int | null $from, string $to, string $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        self::assertSame($to, $transformer->transform($from));
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     *
     * @test
     */
    public function transform_with_grouping(float $from, string $to, string $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        self::assertSame($to, $transformer->transform($from));
    }

    /**
     * @test
     */
    public function transform_with_scale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(2);

        self::assertEquals('1234,50', $transformer->transform(1234.5));
        self::assertEquals('678,92', $transformer->transform(678.916));
    }

    /**
     * @dataProvider transformWithRoundingProvider
     *
     * @test
     */
    public function transform_with_rounding($scale, $input, $output, $roundingMode): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        self::assertEquals($output, $transformer->transform($input));
    }

    public static function transformWithRoundingProvider(): iterable
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, 1234.4, '1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [0, -1234.4, '-1235', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, 123.44, '123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            [1, -123.44, '-123,5', NumberToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1233.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, 1232.5, '1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1233.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, -1232.5, '-1232', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.35, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, 123.25, '123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.35, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, -123.25, '-123,2', NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, 1234.5, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.5, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.45, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.45, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            [0, 1234.6, '1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, 1234.5, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, 1234.4, '1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.6, '-1235', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.5, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, -1234.4, '-1234', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.46, '123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.45, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, 123.44, '123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.46, '-123,5', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.45, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, -123.44, '-123,4', NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @test
     */
    public function transform_does_not_round_if_no_scale(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, false, NumberToLocalizedStringTransformer::ROUND_DOWN);

        self::assertEquals('1234,547', $transformer->transform(1234.547));
    }

    /**
     * @dataProvider provideTransformations
     *
     * @test
     */
    public function reverse_transform($to, $from, $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer();

        self::assertEquals($to, $transformer->reverseTransform($from));
    }

    public static function provideTransformations(): iterable
    {
        return [
            [null, '', 'de_AT'],
            [1, '1', 'de_AT'],
            [1.5, '1,5', 'de_AT'],
            [1234.5, '1234,5', 'de_AT'],
            [12345.912, '12345,912', 'de_AT'],
            [1234.5, '1234,5', 'ru'],
            [1234.5, '1234,5', 'fi'],
        ];
    }

    /**
     * @dataProvider provideTransformationsWithGrouping
     *
     * @test
     */
    public function reverse_transform_with_grouping($to, $from, $locale): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault($locale);

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        self::assertEquals($to, $transformer->reverseTransform($from));
    }

    public static function provideTransformationsWithGrouping(): iterable
    {
        return [
            [1234.5, '1.234,5', 'de_DE'],
            [12345.912, '12.345,912', 'de_DE'],
            [1234.5, '1 234,5', 'fr'],
            [1234.5, '1 234,5', 'ru'],
            [1234.5, '1 234,5', 'fi'],
        ];
    }

    /**
     * @see https://github.com/symfony/symfony/issues/7609
     *
     * @test
     */
    public function reverse_transform_with_grouping_and_fixed_spaces(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        self::assertEquals(1234.5, $transformer->reverseTransform("1\xc2\xa0234,5"));
    }

    /**
     * @test
     */
    public function reverse_transform_with_grouping_but_without_group_separator(): void
    {
        // Since we test against "de_AT", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_AT');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // omit group separator
        self::assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        self::assertEquals(12345.912, $transformer->reverseTransform('12345,912'));
    }

    /**
     * @dataProvider reverseTransformWithRoundingProvider
     *
     * @test
     */
    public function reverse_transform_with_rounding($scale, $input, $output, $roundingMode): void
    {
        $transformer = new NumberToLocalizedStringTransformer($scale, null, $roundingMode);

        self::assertEquals($output, $transformer->reverseTransform($input));
    }

    public static function reverseTransformWithRoundingProvider(): iterable
    {
        return [
            // towards positive infinity (1.6 -> 2, -1.6 -> -1)
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_CEILING],
            // towards negative infinity (1.6 -> 1, -1.6 -> -2)
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            [1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_FLOOR],
            // away from zero (1.6 -> 2, -1.6 -> 2)
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '1234,4', 1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [0, '-1234,4', -1235, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '123,44', 123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            [1, '-123,44', -123.5, NumberToLocalizedStringTransformer::ROUND_UP],
            // towards zero (1.6 -> 1, -1.6 -> -1)
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_DOWN],
            // round halves (.5) to the next even number
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1233,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '1232,5', 1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1233,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [0, '-1232,5', -1232, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,35', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '123,25', 123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,35', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            [1, '-123,25', -123.2, NumberToLocalizedStringTransformer::ROUND_HALF_EVEN],
            // round halves (.5) away from zero
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '1234,5', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,5', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,45', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,45', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_UP],
            // round halves (.5) towards zero
            [0, '1234,6', 1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '1234,5', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '1234,4', 1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,6', -1235, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,5', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [0, '-1234,4', -1234, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,46', 123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,45', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '123,44', 123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,46', -123.5, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,45', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
            [1, '-123,44', -123.4, NumberToLocalizedStringTransformer::ROUND_HALF_DOWN],
        ];
    }

    /**
     * @test
     */
    public function reverse_transform_does_not_round_if_no_scale(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, false, NumberToLocalizedStringTransformer::ROUND_DOWN);

        self::assertEquals(1234.547, $transformer->reverseTransform('1234,547'));
    }

    /**
     * @test
     */
    public function decimal_separator_may_be_dot_if_grouping_separator_is_not_dot(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // completely valid format
        self::assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // accept dots
        self::assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // omit group separator
        self::assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        self::assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @test
     */
    public function decimal_separator_may_not_be_dot_if_grouping_separator_is_dot(): void
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('1.234.5');
    }

    /**
     * @test
     */
    public function decimal_separator_may_not_be_dot_if_grouping_separator_is_dot_with_no_group_sep(): void
    {
        // Since we test against "de_DE", we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('de_DE');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('1234.5');
    }

    /**
     * @test
     */
    public function decimal_separator_may_be_dot_if_grouping_separator_is_dot_but_no_grouping_used(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('fr');
        $transformer = new NumberToLocalizedStringTransformer();

        self::assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        self::assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @test
     */
    public function decimal_separator_may_be_comma_if_grouping_separator_is_not_comma(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('bg');
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        // completely valid format
        self::assertEquals(1234.5, $transformer->reverseTransform('1 234.5'));
        // accept commas
        self::assertEquals(1234.5, $transformer->reverseTransform('1 234,5'));
        // omit group separator
        self::assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
        self::assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
    }

    /**
     * @test
     */
    public function decimal_separator_may_not_be_comma_if_grouping_separator_is_comma(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('1,234,5');
    }

    /**
     * @test
     */
    public function decimal_separator_may_not_be_comma_if_grouping_separator_is_comma_with_no_group_sep(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('1234,5');
    }

    /**
     * @test
     */
    public function decimal_separator_may_be_comma_if_grouping_separator_is_comma_but_no_grouping_used(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        self::assertEquals(1234.5, $transformer->reverseTransform('1234,5'));
        self::assertEquals(1234.5, $transformer->reverseTransform('1234.5'));
    }

    /**
     * @test
     */
    public function transform_expects_numeric(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->transform('foo');
    }

    /**
     * @test
     */
    public function reverse_transform_expects_string(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform(1);
    }

    /**
     * @test
     */
    public function reverse_transform_expects_valid_number(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('foo');
    }

    /**
     * @see https://github.com/symfony/symfony/issues/3161
     *
     * @test
     */
    public function reverse_transform_disallows_na_n(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('NaN');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_na_n2(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('nan');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_infinity(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('∞');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_infinity2(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('∞,123');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_negative_infinity(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('-∞');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_leading_extra_characters(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('foo123');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_centered_extra_characters(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo3"');

        $transformer->reverseTransform('12foo3');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_centered_extra_characters_multibyte(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/The number contains unrecognized characters: "f?oo8"/');

        $transformer->reverseTransform("12\xc2\xa0345,67foo8");
    }

    /**
     * @test
     */
    public function reverse_transform_ignores_trailing_spaces_in_exception_message(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/The number contains unrecognized characters: "f?oo8"/');

        $transformer->reverseTransform("12\xc2\xa0345,67foo8  \xc2\xa0\t");
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_trailing_extra_characters(): void
    {
        $transformer = new NumberToLocalizedStringTransformer();

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The number contains unrecognized characters: "foo"');

        $transformer->reverseTransform('123foo');
    }

    /**
     * @test
     */
    public function reverse_transform_disallows_trailing_extra_characters_multibyte(): void
    {
        // Since we test against other locales, we need the full implementation
        IntlTestHelper::requireFullIntl($this, '70.1');

        \Locale::setDefault('ru');

        $transformer = new NumberToLocalizedStringTransformer(null, true);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/The number contains unrecognized characters: "f?oo"/');

        $transformer->reverseTransform("12\xc2\xa0345,678foo");
    }

    /**
     * @test
     */
    public function reverse_transform_big_int(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        self::assertEquals(\PHP_INT_MAX - 1, (int) $transformer->reverseTransform((string) (\PHP_INT_MAX - 1)));
    }

    /**
     * @test
     */
    public function reverse_transform_small_int(): void
    {
        $transformer = new NumberToLocalizedStringTransformer(null, true);

        self::assertSame(1.0, $transformer->reverseTransform('1'));
    }
}
