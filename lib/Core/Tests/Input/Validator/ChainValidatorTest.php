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

namespace Rollerworks\Component\Search\Tests\Input\Validator;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\FieldSet;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\Input\Validator;
use Rollerworks\Component\Search\Input\Validator\ChainValidator;
use Rollerworks\Component\Search\Value\Compare;

/**
 * @internal
 */
final class ChainValidatorTest extends TestCase
{
    /**
     * @test
     */
    public function it_works_with_empty_chain(): void
    {
        $validator = new ChainValidator();

        $this->assertTrue($validator->validate('foo', 'simpleValue', 'foo', ''));
        $this->assertTrue($validator->validate('foo2', 'simpleValue', 'foo', ''));
    }

    /**
     * @test
     */
    public function it_executes_validators(): void
    {
        $validator1 = new MockValidator(valid: true);
        $validator2 = new MockValidator(valid: true);
        $validator3 = new MockValidator(valid: true);
        $validator4 = new MockValidator(valid: 'yes');

        $validator = new ChainValidator([$validator1, $validator2, $validator3, $validator4]);
        $validator->initialize($config = new ProcessorConfig($this->createMock(FieldSet::class)));
        $validator->initializeContext(
            $field = $this->createMock(FieldConfig::class),
            $errorList = new ErrorList()
        );

        $this->assertFalse($validator->validate('no', 'simpleValue', 'nod', 'val2'));
        $this->assertFalse($validator->validate('no', Compare::class, 'nod', 'val3'));
        $this->assertTrue($validator->validate('yes', 'simpleValue', 'yesed', 'val1'));

        $expected = [
            ['initialize', $config],
            ['initializeContext', $field, $errorList],
            ['validate', 'no', 'simpleValue', 'nod', 'val2'],
            ['validate', 'no', Compare::class, 'nod', 'val3'],
            ['validate', 'yes', 'simpleValue', 'yesed', 'val1'],
        ];

        // Each validator is expected to receive the exact same arguments
        $this->assertSame($expected, $validator1->calls);
        $this->assertSame($expected, $validator2->calls);
        $this->assertSame($expected, $validator3->calls);
        $this->assertSame($expected, $validator4->calls);
    }

    /**
     * @test
     */
    public function it_stops_on_failure(): void
    {
        $validator1 = new MockValidator(valid: true);
        $validator2 = new MockValidator(valid: 'yes');
        $validator3 = new MockValidator(valid: true);
        $validator4 = new MockValidator(valid: true);

        $validator = new ChainValidator([$validator1, $validator2, $validator3, $validator4]);
        $validator->initialize($config = new ProcessorConfig($this->createMock(FieldSet::class)));

        $validator->initializeContext(
            $field = $this->createMock(FieldConfig::class),
            $errorList = new ErrorList()
        );

        $this->assertFalse($validator->validate('no', 'simpleValue', 'nod', 'val2'));
        $this->assertFalse($validator->validate('no', Compare::class, 'nod', 'val3'));

        $validator->initializeContext(
            $field2 = $this->createMock(FieldConfig::class),
            $errorList2 = new ErrorList()
        );

        $this->assertTrue($validator->validate('yes', 'simpleValue', 'yesed', 'val1'));

        $expected = [
            ['initialize', $config],

            ['initializeContext', $field, $errorList],
            ['validate', 'no', 'simpleValue', 'nod', 'val2'],
            ['validate', 'no', Compare::class, 'nod', 'val3'],

            ['initializeContext', $field2, $errorList2],
            ['validate', 'yes', 'simpleValue', 'yesed', 'val1'],
        ];

        // Because $validator2 marked value 'no' as invalid the chain was stopped
        // The first run with 'yes' was valid and part of the calls, as each validation only
        // applies per value value validation
        $expectedInvalid = [
            ['initialize', $config],
            ['initializeContext', $field, $errorList],
            // Call to validate('no') was stopped...
            ['initializeContext', $field2, $errorList2],
            ['validate', 'yes', 'simpleValue', 'yesed', 'val1'],
        ];

        $this->assertSame($expected, $validator1->calls);
        $this->assertSame($expected, $validator2->calls);
        $this->assertSame($expectedInvalid, $validator3->calls);
        $this->assertSame($expectedInvalid, $validator4->calls);
    }

    /**
     * @test
     */
    public function it_continues_on_failure_when_configured(): void
    {
        $validator1 = new MockValidator(valid: true);
        $validator2 = new MockValidator(valid: 'yes');
        $validator3 = new MockValidator(valid: true);
        $validator4 = new MockValidator(valid: true);

        $validator = new ChainValidator([$validator1, $validator2, $validator3, $validator4]);

        $config = new ProcessorConfig($this->createMock(FieldSet::class));
        $config->setAttribute(ChainValidator::CONTINUE_ON_FAILURE, true);

        $validator->initialize($config);
        $validator->initializeContext(
            $field = $this->createMock(FieldConfig::class),
            $errorList = new ErrorList()
        );

        $this->assertFalse($validator->validate('no', 'simpleValue', 'nod', 'val2'));
        $this->assertFalse($validator->validate('no', Compare::class, 'nod', 'val3'));

        $validator->initializeContext(
            $field2 = $this->createMock(FieldConfig::class),
            $errorList2 = new ErrorList()
        );

        $this->assertTrue($validator->validate('yes', 'simpleValue', 'yesed', 'val1'));

        $expected = [
            ['initialize', $config],

            ['initializeContext', $field, $errorList],
            ['validate', 'no', 'simpleValue', 'nod', 'val2'],
            ['validate', 'no', Compare::class, 'nod', 'val3'],

            ['initializeContext', $field2, $errorList2],
            ['validate', 'yes', 'simpleValue', 'yesed', 'val1'],
        ];

        $this->assertSame($expected, $validator1->calls);
        $this->assertSame($expected, $validator2->calls);
        $this->assertSame($expected, $validator3->calls);
        $this->assertSame($expected, $validator4->calls);
    }
}

/**
 * @internal
 */
final class MockValidator implements Validator
{
    /** @var array<string, mixed[]> */
    public array $calls = [];

    public function __construct(
        private bool | string $valid = true,
    ) {}

    public function initialize(ProcessorConfig $config): void
    {
        $this->calls[] = ['initialize', $config];
    }

    public function initializeContext(FieldConfig $field, ErrorList $errorList): void
    {
        $this->calls[] = ['initializeContext', $field, $errorList];
    }

    public function validate($value, string $type, $originalValue, string $path): bool
    {
        $this->calls[] = ['validate', $value, $type, $originalValue, $path];

        if (\is_string($this->valid)) {
            return $this->valid === $value;
        }

        return $this->valid;
    }
}
