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
use Rollerworks\Component\Search\InputProcessor;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchOrder;

/**
 * AbstractInput provides the shared logic for the InputProcessors.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
abstract class AbstractInput implements InputProcessor
{
    protected ProcessorConfig $config;
    protected Validator $validator;
    protected ErrorList $errors;

    /** Current nesting level. */
    protected int $level = 0;

    public function __construct(?Validator $validator = null)
    {
        $this->validator = $validator ?? new NullValidator();

        if ($validator && ! method_exists($validator, 'initialize')) {
            trigger_deprecation('rollerworks/search', '2.1', 'For "%s" class not implementing the initialize() public method is deprecated and will be required in v3.0.', $validator::class);
        }
    }

    /**
     * Finalize the ordering of the fields.
     *
     * - Sets the default ordering if no ordering is set.
     * - Ensures that the always-ordered fields are present.
     */
    public static function finalizeOrdering(SearchCondition $condition): void
    {
        $ordering = $condition->getOrder()?->getFields() ?? [];
        $hasOrder = $ordering !== [];

        $apply = $hasOrder;
        $prepend = $append = [];

        foreach ($condition->getFieldSet()->all() as $field) {
            $name = $field->getName();

            if (! $condition->getFieldSet()->isOrder($name)) {
                continue;
            }

            $default = $field->getOption('default');

            if ($default === null) {
                continue;
            }

            $always = $field->getOption('always');

            if ($always === 'prepend') {
                $prepend[$name] = $default;
                $apply = true;
            } elseif ($always === 'append') {
                $append[$name] = $default;
                $apply = true;
            } elseif (! $hasOrder) {
                $ordering[$name] = $default;
                $apply = true;
            }
        }

        if ($apply) {
            $condition->setOrder(new SearchOrder($ordering, $prepend, $append));
        }
    }

    /**
     * This method is called after processing and helps with finding bugs.
     */
    protected function assertLevel0(): void
    {
        if ($this->level > 0) {
            throw new \RuntimeException('Level nesting is not reset to 0, please report this bug.');
        }
    }
}
