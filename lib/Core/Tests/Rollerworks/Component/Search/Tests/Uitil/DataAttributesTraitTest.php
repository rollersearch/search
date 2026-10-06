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

namespace Rollerworks\Component\Search\Tests\Uitil;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Util\DataAttributesTrait;

/**
 * @internal
 */
final class DataAttributesTraitTest extends TestCase
{
    /**
     * @test
     */
    public function it_sets_attributes(): void
    {
        $object = new DataAttributesImpl();
        $object->setAttribute('foo', 'bar');
        $object->setAttribute('baz', 'qux');
        $object->setAttribute('bing', null);

        $object->setAttribute(TestConfig::Never, 'hurt');
        $object->setAttribute(TestConfig::Give, 'up');

        self::assertSame('bar', $object->getAttribute('foo'));
        self::assertSame('qux', $object->getAttribute('baz'));
        self::assertSame('hurt', $object->getAttribute(TestConfig::Never));
        self::assertSame('qux', $object->getAttributeOrFail('baz'));
        self::assertNull($object->getAttribute('bing', 'nope'));

        // --
        self::assertNull($object->getAttribute('non-existing'));
        self::assertFalse($object->getAttribute('non-existing', false));

        self::assertTrue($object->hasAttribute('foo'));
        self::assertTrue($object->hasAttribute(TestConfig::Never));
        self::assertFalse($object->hasAttribute('non-existing'));
        self::assertFalse($object->hasAttribute(TestConfig::Gonna));

        self::assertSame([
            'foo' => 'bar',
            'baz' => 'qux',
            'bing' => null,
            TestConfig::class . '::Never' => 'hurt',
            TestConfig::class . '::Give' => 'up',
        ], $object->getAttributes());
    }

    /**
     * @test
     */
    public function it_removes_attribute(): void
    {
        $object = new DataAttributesImpl();
        $object->setAttribute('foo', 'bar');
        $object->setAttribute('baz', 'qux');
        $object->setAttribute(TestConfig::Never, 'hurt');
        $object->setAttribute(TestConfig::Give, 'up');

        $object->removeAttribute('foo');
        $object->removeAttribute(TestConfig::Never);

        self::assertTrue($object->hasAttribute('baz'));
        self::assertTrue($object->hasAttribute(TestConfig::Give));
        self::assertFalse($object->hasAttribute('foo'));
        self::assertFalse($object->hasAttribute(TestConfig::Never));
    }

    /**
     * @test
     */
    public function it_returns_default_value_when_attribute_does_not_exist(): void
    {
        $object = new DataAttributesImpl();

        self::assertNull($object->getAttribute('bing'));
        self::assertSame('nope', $object->getAttribute('bing', 'nope'));
    }

    /**
     * @test
     */
    public function it_fails_when_attribute_does_not_exist(): void
    {
        $object = new DataAttributesImpl();

        self::assertFalse($object->hasAttribute('bing'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute "bing" does not exist.');

        $object->getAttributeOrFail('bing');
    }

    /**
     * @test
     */
    public function it_fails_when_enum_attribute_does_not_exist(): void
    {
        $object = new DataAttributesImpl();

        self::assertFalse($object->hasAttribute(TestConfig::YouUp));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Attribute "%s::YouUp" does not exist.', TestConfig::class));

        $object->getAttributeOrFail(TestConfig::YouUp);
    }
}

/**
 * @internal
 */
final class DataAttributesImpl
{
    use DataAttributesTrait;
}

/**
 * @internal
 */
enum TestConfig
{
    case Never;

    case Gonna;

    case Give;

    case YouUp;
}
