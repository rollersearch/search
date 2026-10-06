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

namespace Rollerworks\Component\Search\Extension\Symfony\Validator;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Input\Validator;
use Rollerworks\Component\Search\Value\PatternMatch;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validates input values using the Symfony Validator.
 *
 * The search field must have a `constraints` option set
 * or else it's ignored.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class InputValidator implements Validator
{
    private ErrorList $errorList;

    /** @var Constraint[]|null */
    private ?array $constraints = [];

    /** @var Constraint[]|null */
    private ?array $patternMatchConstraints = null;

    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    public function initializeContext(FieldConfig $field, ErrorList $errorList): void
    {
        $this->errorList = $errorList;
        $this->constraints = $field->getOption('constraints');
        $this->patternMatchConstraints = $field->getOption('pattern_match_constraints');
    }

    public function validate($value, string $type, $originalValue, string $path): bool
    {
        $constraints = $type === PatternMatch::class ? $this->patternMatchConstraints : $this->constraints;

        if ($constraints === null) {
            return true;
        }

        $violations = $this->validator->validate($value, $constraints);

        foreach ($violations as $violation) {
            $this->errorList[] = new ConditionErrorMessage(
                $path,
                $violation->getMessage(),
                $violation->getMessageTemplate(),
                $violation->getParameters(),
                $violation->getPlural(),
                $violation
            );
        }

        return ! \count($violations);
    }
}
