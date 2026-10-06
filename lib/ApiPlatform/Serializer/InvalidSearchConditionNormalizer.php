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

namespace Rollerworks\Component\Search\ApiPlatform\Serializer;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Converts {@see InvalidSearchConditionException} to a Hydra error representation.
 *
 * @deprecated use the {@see ConditionErrorMessageNormalizer} with the
 *             {@see \Rollerworks\Component\Search\ApiPlatform\Exception\InvalidConditionException}
 *             as resource handle instead
 */
final class InvalidSearchConditionNormalizer implements NormalizerInterface
{
    /**
     * @param string[]|null $serializePayloadFields
     */
    public function __construct(
        private readonly ?array $serializePayloadFields = null,
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {}

    /**
     * @return array{type: string, title: string, detail: string, violations: array<array{propertyPath: string, message: string, payload?: array<string, mixed>}>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $list = new ConstraintViolationList();

        /** @var ConditionErrorMessage $message */
        foreach ($data->getErrors() as $message) {
            $violation = new ConstraintViolation(
                $message->message,
                $message->messageTemplate,
                $message->messageParameters,
                null, // root
                $message->path,
                null, // invalidValue
                $message->messagePluralization,
                null, // code
                null, // constraint
                $message->cause
            );
            $list->add($violation);
        }

        [$messages, $violations] = $this->getMessagesAndViolations($list);

        return [
            'type' => $context['type'] ?? 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => $context['title'] ?? 'An error occurred',
            'detail' => $messages ? implode("\n", $messages) : (string) $data,
            'violations' => $violations,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($format === 'json' || $format === 'jsonproblem') && $data instanceof InvalidSearchConditionException;
    }

    /**
     * @author Kévin Dunglas <dunglas@gmail.com>
     *
     * @see https://github.com/api-platform/core
     */
    private function getMessagesAndViolations(ConstraintViolationListInterface $constraintViolationList): array
    {
        $violations = $messages = [];

        foreach ($constraintViolationList as $violation) {
            $violationData = [
                'propertyPath' => $this->nameConverter ? $this->nameConverter->normalize($violation->getPropertyPath()) : $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];

            $constraint = $violation->getConstraint();

            if ($this->serializePayloadFields && $constraint && $constraint->payload) {
                // If some fields are whitelisted, only them are added
                $payloadFields = $this->serializePayloadFields === null ? $constraint->payload : array_intersect_key($constraint->payload, array_flip($this->serializePayloadFields));
                $payloadFields && $violationData['payload'] = $payloadFields;
            }

            $violations[] = $violationData;
            $messages[] = ($violationData['propertyPath'] ? "{$violationData['propertyPath']}: " : '') . $violationData['message'];
        }

        return [$messages, $violations];
    }

    public function getSupportedTypes(?string $format): array
    {
        if ($format === 'json' || $format === 'jsonproblem') {
            return [
                InvalidSearchConditionException::class => true,
            ];
        }

        return [];
    }
}
