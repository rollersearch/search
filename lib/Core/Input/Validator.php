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

namespace Rollerworks\Component\Search\Input;

use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Field\FieldConfig;

/**
 * The Validator validates field values.
 *
 * The validator is first initialized with the {@see ProcessorConfig}
 * using the `initialize()` method (when present).
 *
 * Note: Not implementing the public `initialize()` method is deprecated and will be required in v3.0.
 *
 * For each field (current) the validator is initialized with `initializeContext()`,
 * then for each value in the current field the `validate()` method is called.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @method void initialize(ProcessorConfig $config)
 */
interface Validator
{
    /**
     * Initialize the validator context for the field.
     *
     * This method is called prior to the first call to validate().
     * Errors need to be added to the ErrorList.
     */
    public function initializeContext(FieldConfig $field, ErrorList $errorList): void;

    /**
     * Validates and returns whether the value is valid.
     *
     * Any error (or violation) should be added to the ErrorList with the
     * corresponding path. Multiple errors can be added for the same path.
     *
     * Example:
     * ```
     * // ErrorList was initialized in initializeContext()
     *
     * $this->errorList->append(new \Rollerworks\Component\Search\ConditionErrorMessage(
     *      $path,
     *      $violation->getMessage(),
     *      $violation->getMessageTemplate(),
     *      $violation->getParameters(),
     *      $violation->getPlural(),
     *      $violation, // Cause of the error (can be anything), optional used for debugging and profiling
     * ));
     * ```
     */
    public function validate($value, string $type, $originalValue, string $path): bool;
}
