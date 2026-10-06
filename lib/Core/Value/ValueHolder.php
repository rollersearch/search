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

namespace Rollerworks\Component\Search\Value;

/**
 * ValueHolder is the marker interface for all value-holders.
 *
 * Note that all value-holders are considered read-only.
 *
 * Do not use this interface directly for any custom implementations
 * as this is not supported.
 */
interface ValueHolder {}
