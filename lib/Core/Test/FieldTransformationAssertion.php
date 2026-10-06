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

namespace Rollerworks\Component\Search\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Field\FieldConfig;

/**
 * The FieldTransformationAssertion class provides a fluent interface for
 * testing field transformations.
 *
 * Both inputView and inputNorm are expected be strings, when no
 * inputNorm is provided the inputView is used as the norm value.
 *
 * Example:
 *
 * ```
 * FieldTransformationAssertion::assertThat($field)
 *      ->withInput('value')
 *      ->successfullyTransformsTo('transformed-value')
 *
 *      // Should be equal to the input (but may vary), and should still transform to the same value.
 *      ->andReverseTransformsTo('value')
 *
 *      // Or to expect a transformation failure:
 *      ->failsToTransforms(new TransformationFailedException('message'))
 * ```
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class FieldTransformationAssertion
{
    private string $inputView;
    private string $inputNorm;
    private bool $transformed = false;
    private mixed $model;

    private function __construct(
        private readonly FieldConfig $field,
    ) {}

    public static function assertThat(FieldConfig $field): self
    {
        return new self($field);
    }

    public function withInput(string $inputView, ?string $inputNorm = null): self
    {
        if ($this->transformed) {
            throw new \LogicException('Cannot change input after transformation.');
        }

        $this->inputView = $inputView;
        $this->inputNorm = $inputNorm ?? $this->inputView;

        return $this;
    }

    public function successfullyTransformsTo(mixed $model): self
    {
        $normValue = $viewValue = null;

        if (! isset($this->inputView)) {
            throw new \LogicException('withInput() must be called first.');
        }

        try {
            $viewValue = $this->viewToModel($this->inputView);
        } catch (TransformationFailedException $e) {
            Assert::fail('View->model: With input ' . var_export($this->inputView, true) . '. Message ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        try {
            $normValue = $this->normToModel($this->inputNorm);
        } catch (TransformationFailedException $e) {
            Assert::fail('Norm->model: With input ' . var_export($this->inputNorm, true) . '. Message ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        Assert::assertEquals($model, $viewValue, 'View->model value does not equal');
        Assert::assertEquals($model, $normValue, 'Norm->model value does not equal');

        $this->transformed = true;
        $this->model = $model;

        return $this;
    }

    public function failsToTransforms(?TransformationFailedException $exceptionForView = null, ?TransformationFailedException $exceptionForModel = null): void
    {
        if (! isset($this->inputView)) {
            throw new \LogicException('withInput() must be called first.');
        }

        if ($this->transformed) {
            throw new \LogicException('Only successfullyTransformsTo() or failsToTransforms() can be called.');
        }

        try {
            $this->viewToModel($this->inputView);

            Assert::fail(\sprintf('Expected view-input "%s" to be invalid', $this->inputView));
        } catch (TransformationFailedException $e) {
            if ($exceptionForView) {
                self::assertTransformationFailedExceptionEquals($exceptionForView, $e);
            } else {
                Assert::assertTrue(true); // no-op
            }
        }

        try {
            $this->normToModel($this->inputNorm);

            Assert::fail(\sprintf('Expected norm-input "%s" to be invalid', $this->inputNorm));
        } catch (TransformationFailedException $e) {
            if ($exceptionForModel) {
                self::assertTransformationFailedExceptionEquals($exceptionForModel, $e);
            } else {
                Assert::assertTrue(true); // no-op
            }
        }
    }

    public function andReverseTransformsTo(?string $expectedView = null, ?string $expectedNorm = null): void
    {
        $normValue = $viewValue = null;

        if (! $this->transformed) {
            throw new \LogicException('successfullyTransformsTo() must be called first.');
        }

        try {
            $viewValue = $this->modelToView($this->model);
        } catch (TransformationFailedException $e) {
            Assert::fail('Model->view: With value ' . var_export($this->model, true) . '. Message ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        try {
            $normValue = $this->modelToNorm($this->model);
        } catch (TransformationFailedException $e) {
            Assert::fail('Model->norm: With value ' . var_export($this->model, true) . '. Message ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        Assert::assertEquals($expectedView, $viewValue, 'View value does not equal');
        Assert::assertEquals($expectedNorm ?? $expectedView, $normValue, 'Norm value does not equal');
    }

    private function viewToModel(string $value)
    {
        $transformer = $this->field->getViewTransformer();

        return $transformer ? $transformer->reverseTransform($value) : ($value === '' ? null : $value);
    }

    private function modelToView(mixed $value): string
    {
        $transformer = $this->field->getViewTransformer();

        // Scalar values should be converted to strings to
        // facilitate differentiation between empty ("") and zero (0).
        if ($value === null || ! $transformer) {
            return (string) $value;
        }

        return (string) $transformer->transform($value);
    }

    private function normToModel(string $value): mixed
    {
        $transformer = $this->field->getNormTransformer() ?? $this->field->getViewTransformer();

        return ! $transformer ? $value : $transformer->reverseTransform($value);
    }

    private function modelToNorm(mixed $value): string
    {
        $transformer = $this->field->getNormTransformer() ?? $this->field->getViewTransformer();

        // Scalar values should be converted to strings to
        // facilitate differentiation between empty ("") and zero (0).
        if ($value === null || ! $transformer) {
            return (string) $value;
        }

        return (string) $transformer->transform($value);
    }

    private static function assertTransformationFailedExceptionEquals(TransformationFailedException $expected, TransformationFailedException $actual): void
    {
        try {
            if ($expected->getPrevious()) {
                Assert::assertEquals($expected->getPrevious(), $actual->getPrevious(), 'Previous exception does not equal.');
            }

            Assert::assertEquals($expected->getMessage(), $actual->getMessage(), 'Message does not equal.');
            Assert::assertEquals($expected->getCode(), $actual->getCode(), 'Code does not equal.');
            Assert::assertEquals($expected->getInvalidMessage(), $actual->getInvalidMessage(), 'Invalid message does not equal.');
            Assert::assertEquals($expected->getInvalidMessageParameters(), $actual->getInvalidMessageParameters(), 'Invalid-messages parameters does not equal.');

            if ($expected->getInvalidValue() !== null) {
                Assert::assertEquals($expected->getInvalidValue(), $actual->getInvalidValue(), 'Invalid value does not equal.');
            }
        } catch (ExpectationFailedException $e) {
            Assert::assertEquals($expected, $actual, $e->getMessage());
        }
    }
}
