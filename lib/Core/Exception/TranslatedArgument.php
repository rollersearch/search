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

namespace Rollerworks\Component\Search\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatedArgument implements TranslatableInterface, \Stringable
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly string $message,
        private readonly array $parameters = [],
        private readonly ?string $domain = null,
    ) {}

    public function __toString(): string
    {
        return $this->getMessage();
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getMessage(), array_map(
            static fn ($parameter) => $parameter instanceof TranslatableInterface ? $parameter->trans($translator, $locale) : $parameter,
            $this->getParameters()
        ), $this->getDomain(), $locale);
    }
}
