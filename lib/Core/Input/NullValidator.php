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

final class NullValidator implements Validator
{
    public function initialize(ProcessorConfig $config): void
    {
        // no-op
    }

    public function initializeContext(FieldConfig $field, ErrorList $errorList): void
    {
        // no-op
    }

    public function validate($value, string $type, $originalValue, string $path): bool
    {
        return true;
    }
}
