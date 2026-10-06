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

namespace Rollerworks\Component\Search;

/**
 * Entry point of the Search system.
 *
 * Use this class to conveniently create new search factories:
 *
 * <code>
 * use Rollerworks\Component\Search\Searches;
 * use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
 * use Rollerworks\Component\Search\Extension\Core\Type\TextType;
 * use Rollerworks\Component\Search\Extension\Core\Type\IntegerType;
 * use Rollerworks\Component\Search\Extension\Core\Type\ChoiceType;
 * use Rollerworks\Component\Search\Input\StringQueryInput;
 *
 * // The factory is reusable, you create it only once.
 * $searchFactory = Searches::createSearchFactory();
 *
 * // Create a fieldset to inform the system about your configuration.
 * // Usually you will have a FieldSet for each data structure (users, invoices, etc).
 * $userFieldSet = $searchFactory->createFieldSetBuilder()
 *     ->add('firstName', TextType::class)
 *     ->add('lastName', TextType::class)
 *     ->add('age', IntegerType::class)
 *     ->add('gender', ChoiceType::class, [
 *         'choices' => ['Female' => 'f', 'Male' => 'm'],
 *     ])
 *     ->getFieldSet('users');
 *
 * // Now lets process a simple string query.
 * // Tip: the input processor is reusable.
 * $inputProcessor = new StringQueryInput();
 *
 * try {
 *     // The ProcessorConfig allows to limit the amount of values, groups
 *     // and maximum nesting level.
 *     $processorConfig = new ProcessorConfig($userFieldSet);
 *
 *     // The `process` method processes the input and produces
 *     // a valid SearchCondition (or throws an exception when something is wrong).
 *     $condition = $inputProcessor->process('firstName: sebastiaan, melany;');
 * } catch (InvalidSearchConditionException $e) {
 *     // Each error message can be easily transformed to a localized version.
 *     // Read the documentation for more details.
 *     foreach ($e->getErrors() as $error) {
 *         echo (string) $error.PHP_EOL;
 *     }
 * }
 * </code>
 *
 * You can also add custom extensions to the search factory:
 *
 * <code>
 * $searchFactory = Searches::createSearchFactoryBuilder();
 *     ->addExtension(new AcmeExtension())
 *     ->getSearchFactory();
 * </code>
 *
 * If you create custom field types or type extensions, it is
 * generally recommended to create your own extensions that lazily
 * loads these types and type extensions. In projects where performance
 * does not matter that much, you can also pass them directly to the
 * search factory:
 *
 * <code>
 * use Rollerworks\Component\Search\Searches;
 *
 * $searchFactory = Searches::createSearchFactoryBuilder();
 *     ->addType(new PersonType())
 *     ->addType(new PhoneNumberType())
 *     ->addTypeExtension(new DoctrineDbalExtension())
 *     ->getSearchFactory();
 * </code>
 *
 * Tip: Field types without dependency injection don't have to be
 * registered with the factory and can be loaded by there FQCN.
 * Field type-extensions must always be registered.
 *
 * Support for the Validator component is provided by ValidatorExtension,
 * located in another repository.
 *
 * This extension needs a Validator object to function properly:
 *
 * <code>
 * use Rollerworks\Component\Search\Searches;
 * use Rollerworks\Component\Search\Extension\Validator\ValidatorExtension;
 * use Rollerworks\Component\Search\Extension\Validator\InputValidator;
 * use Rollerworks\Component\Search\Input\StringQueryInput;
 * use Symfony\Component\Validator\Validation;
 *
 * $searchFactory = Searches::createSearchFactoryBuilder();
 *     ->addExtension(new ValidatorExtension())
 *     ->getSearchFactory();
 *
 * $validatorBuilder = Validation::createValidatorBuilder();
 * $validator = $validatorBuilder->getValidator();
 *
 * $inputProcessor = new StringQueryInput(new InputValidator($validator));
 * </code>
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class Searches
{
    /**
     * Creates a SearchFactoryBuilder with the default configuration.
     */
    public static function createSearchFactoryBuilder(): SearchFactoryBuilder
    {
        return new SearchFactoryBuilder();
    }

    /**
     * Creates a new GenericSearchFactory with the default configuration.
     */
    public static function createSearchFactory(): SearchFactory
    {
        return self::createSearchFactoryBuilder()->getSearchFactory();
    }

    /**
     * This class cannot be instantiated.
     */
    private function __construct() {}
}
