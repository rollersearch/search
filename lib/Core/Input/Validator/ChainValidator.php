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

namespace Rollerworks\Component\Search\Input\Validator;

use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\Input\Validator;

/**
 * The ChainValidator allows to chain multiple validators together.
 *
 * Validators are executed in the order they are added to the chain.
 *
 * If a validator fails, the chain stops and the error is added to the error list.
 * Set `$processorConfig->setAttribute(ChainValidator::CONTINUE_ON_FAILURE, true)`
 * to continue the validation chain.
 */
final class ChainValidator implements Validator
{
    public const CONTINUE_ON_FAILURE = (self::class . ':continue_on_failure');

    private bool $continueOnFailure = false;

    /**
     * @param iterable<Validator> $validators
     */
    public function __construct(
        private readonly iterable $validators = [],
    ) {}

    public function initialize(ProcessorConfig $config): void
    {
        $continueOnFailure = $config->getAttribute(self::CONTINUE_ON_FAILURE, false);

        if (! \is_bool($continueOnFailure)) {
            throw new \InvalidArgumentException(\sprintf('The "%s::CONTINUE_ON_FAILURE" attribute must be a boolean, "%s" given.', self::class, get_debug_type($continueOnFailure)));
        }

        $this->continueOnFailure = $continueOnFailure;

        foreach ($this->validators as $validator) {
            if (method_exists($validator, 'initialize')) {
                $validator->initialize($config);
            } else {
                trigger_deprecation('rollerworks/search', '2.1', 'For "%s" class not implementing the initialize() public method is deprecated and will be required in v3.0.', $validator::class);
            }
        }
    }

    public function initializeContext(FieldConfig $field, ErrorList $errorList): void
    {
        foreach ($this->validators as $validator) {
            $validator->initializeContext($field, $errorList);
        }
    }

    public function validate(mixed $value, string $type, mixed $originalValue, string $path): bool
    {
        $valid = true;

        foreach ($this->validators as $validator) {
            if ($validator->validate($value, $type, $originalValue, $path)) {
                continue;
            }

            if (! $this->continueOnFailure) {
                return false;
            }

            $valid = false;
        }

        return $valid;
    }
}
