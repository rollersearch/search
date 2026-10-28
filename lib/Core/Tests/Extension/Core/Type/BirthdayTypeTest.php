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

use Rollerworks\Component\Search\Extension\Core\Type\BirthdayType;
use Rollerworks\Component\Search\Test\FieldTransformationAssertion;
use Rollerworks\Component\Search\Test\SearchIntegrationTestCase;

/**
 * @internal
 */
final class BirthdayTypeTest extends SearchIntegrationTestCase
{
    /**
     * @test
     */
    public function with_date_and_age(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'pattern' => 'yyyy-MM-dd',
        ]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('2010-06-02', '2010-06-02')
            ->successfullyTransformsTo(new \DateTimeImmutable('2010-06-02'))
            ->andReverseTransformsTo('2010-06-02', '2010-06-02')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('12', '12')
            ->successfullyTransformsTo(12)
            ->andReverseTransformsTo('12', '12')
        ;
    }

    /**
     * @test
     */
    public function date_only_input(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'pattern' => 'yyyy-MM-dd',
            'allow_age' => false,
        ]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('2010-06-02', '2010-06-02')
            ->successfullyTransformsTo(new \DateTimeImmutable('2010-06-02'))
            ->andReverseTransformsTo('2010-06-02', '2010-06-02')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('21')
            ->failsToTransforms()
        ;
    }

    /**
     * @test
     */
    public function with_localized_date(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'locale' => 'nl',
            'allow_age' => false,
            'format' => \IntlDateFormatter::SHORT,
        ]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('2-6-2010', '2010-06-02')
            ->successfullyTransformsTo(new \DateTimeImmutable('2010-06-02'))
            ->andReverseTransformsTo('02-06-2010', '2010-06-02')
        ;
    }

    /**
     * @test
     */
    public function with_non_western_input(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'format' => \IntlDateFormatter::SHORT,
            'locale' => 'ar_BH',
        ]);

        // dump($field->getViewTransformer()->transform(new \DateTimeImmutable('2010-06-02')));

        FieldTransformationAssertion::assertThat($field)
            ->withInput('٢ /٦ /٢٠١٠', '2010-06-02')
            ->successfullyTransformsTo(new \DateTimeImmutable('2010-06-02'))
            ->andReverseTransformsTo("٢\u{200F}/٦\u{200F}/٢٠١٠", '2010-06-02') // 200F = RIGHT-TO-LEFT MARK
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('١٢', '12')
            ->successfullyTransformsTo(12)
            ->andReverseTransformsTo('١٢', '12')
        ;
    }

    /**
     * @test
     */
    public function allow_age_input(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'pattern' => 'yyyy-MM-dd',
        ]);

        FieldTransformationAssertion::assertThat($field)
            ->withInput('2010-06-02', '2010-06-02')
            ->successfullyTransformsTo(new \DateTimeImmutable('2010-06-02'))
            ->andReverseTransformsTo('2010-06-02', '2010-06-02')
        ;

        FieldTransformationAssertion::assertThat($field)
            ->withInput('15')
            ->successfullyTransformsTo(15)
            ->andReverseTransformsTo('15')
        ;
    }

    /**
     * @test
     */
    public function wrong_input_fails(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'allow_age' => true,
        ]);

        FieldTransformationAssertion::assertThat($field)->withInput('twenty')->failsToTransforms();
        FieldTransformationAssertion::assertThat($field)->withInput('-21')->failsToTransforms();
        FieldTransformationAssertion::assertThat($field)->withInput('+21')->failsToTransforms();
    }

    /**
     * @test
     */
    public function age_in_the_future_fails(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'pattern' => 'yyyy-MM-dd',
        ]);

        $currentDate = new \DateTimeImmutable('now + 1 day', new \DateTimeZone('UTC'));

        FieldTransformationAssertion::assertThat($field)->withInput($currentDate->format('Y-m-d'))->failsToTransforms();
    }

    /**
     * @test
     */
    public function age_in_future_works_when_allowed(): void
    {
        $field = $this->getFactory()->createField('birthday', BirthdayType::class, [
            'pattern' => 'yyyy-MM-dd',
            'allow_future_date' => true,
        ]);

        $currentDate = new \DateTimeImmutable('now + 1 day', new \DateTimeZone('UTC'));
        $currentDate = $currentDate->setTime(0, 0, 0);

        FieldTransformationAssertion::assertThat($field)
            ->withInput($currentDate->format('Y-m-d'))
            ->successfullyTransformsTo($currentDate)
            ->andReverseTransformsTo($currentDate->format('Y-m-d'))
        ;
    }
}
