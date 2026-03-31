RollerworksSearch
=================

## About RollerworksSearch

RollerworksSearch is a powerful search-system for PHP.
Created to make searching in a PHP powered application as simple and fast as possible.

Whether you want to search for users in your SQL database, want to provide a 
powerful search system for searching products using an ElasticSearch back-end, 
or are looking for a way to abstract filtering for a reporter.

> [!WARNING]
> Elasticsearch support is [currently broken](https://github.com/rollersearch/search/issues/347)
> and might not be fixed until the next stable release (after v2.0.0).

### How about complex data structures?

A complex data is structure is no problem, say your customer data is stored
in the "customer" table, the "invoices" data is stored in it's own table, and
the details of the invoices have there own table.

Instead of writing a very verbose SQL query, your users can use the easy to learn
StringQuery syntax:

> `invoice-price: > $20.00; invoice-row-label: ~*"my cool product"; customer-type: !consumer`.

You just searched in three relational tables using a single condition with a
user-friendly syntax. And that is just the start, RollerworksSearch can work with
any locale, custom input format, or storage system.

Search conditions can be as simple or complex as you need them to be.
Including grouping and nesting for the best possible result.

## Features

RollerworksSearch provides you with most of the features you can expect
from a search system, including:

* Localized input processing using the StringQuery format;
* User-friendly format validation;
* Integration with API-Platform;
* Integration for Symfony 6.4 and up (Symfony Flex supported).

And support for the most poplar storage systems.

* [Doctrine DBAL](https://github.com/rollerworks/search-doctrine-dbal)
* [Doctrine ORM](https://github.com/rollerworks/search-doctrine-orm)
* [ElasticSearch](https://github.com/rollerworks/search-elasticsearch)

## Installation and usage

*Please ignore the instructions below if your use a framework integration.*

[Read the Documentation][4] for complete instructions and information.

Install the RollerworksSearch "core" library using [Composer][1]:

```bash
$ composer install rollerworks/search
```

And create the `SearchFactory` to get started.

```php
use Rollerworks\Component\Search\Searches;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Rollerworks\Component\Search\Extension\Core\Type\TextType;
use Rollerworks\Component\Search\Extension\Core\Type\IntegerType;
use Rollerworks\Component\Search\Extension\Core\Type\ChoiceType;
use Rollerworks\Component\Search\Input\ErrorPathTranslator;
use Rollerworks\Component\Search\Input\StringQueryInput;
use Rollerworks\Component\Search\Input\ProcessorConfig;

// The factory is reusable, you create it only once.
$searchFactory = Searches::createSearchFactory();

// Create a fieldset to inform the system about your configuration.
// Usually you will have a FieldSet for each data structure (users, invoices, etc).
$userFieldSet = $searchFactory->createFieldSetBuilder()
    ->add('firstName', TextType::class)
    ->add('lastName', TextType::class)
    ->add('age', IntegerType::class)
    ->add('type', ChoiceType::class, [
        'choices' => ['Consumer' => 'c', 'Business' => 'b'],
    ])
    ->getFieldSet('users');
    
// Now lets process a string query.
// Tip: the input processor is reusable.
$inputProcessor = new StringQueryInput();

try {
    // The ProcessorConfig allows to limit the amount of values, groups
    // and maximum nesting level. The defaults should be restrictive enough
    // for most situations.
    $processorConfig = new ProcessorConfig($userFieldSet);
    
    // The `process` method processes the input and produces 
    // a valid SearchCondition (or throws an exception when something is wrong).
    $condition = $inputProcessor->process('firstName: sebastiaan, melany;');
} catch (InvalidSearchConditionException $e) {
    // Each error message can be transformed to a localized version
    // using the Symfony Translator contract.
    
    $translator = ...; // \Symfony\Contracts\Translation\TranslatorInterface
    
    // Note: The ErrorPathHumanizer only works for the `StringQueryInput` input processor.
    $errorPathHumanizer = new ErrorPathTranslator($translator);
    
    foreach ($e->getErrors() as $error) {
       echo '<span>' $errorPathHumanizer->humanize($error->path) .  ' </span><span>' . htmlentities($error->trans($translator), ENT_COMPAT | ENT_IGNORE, 'UTF-8') '</span>' . PHP_EOL;
    }
}
```

That's it! The `$condition` contains the SearchCondition in a normalized
data format which can be used in a condition processor (like ElasticSearch), 
or be exported into another format like JSON for easier usage in a URL.

> **Note:** RollerworksSearch is composed of multiple separate packages (to keep the architecture slim), 
> the "core" package provides everything you need to get started.
>
> Searching a (document) storage requires the installation of additional packages. 

### What about validation?

Each field type ensures the value is transformed to the correct format,
either a date input is automatically transformed to a `DateTimeImuttable` object.

A field that expects an integer will fail when the provided input is not an integer.

To enforce more strict constraints like a maximum amount for an integer field you
can use the [Symfony Validator extension](https://rollerworkssearch.readthedocs.io/en/latest/integration/symfony_validator.html).

## Resources

* [Read the Documentation][4]
* RollerworksSearch is maintained under the [Semantic Versioning guidelines](http://semver.org/)

## Who is behind RollerworksSearch?

RollerworksSearch is brought to you by [Sebastiaan Stok](https://github.com/sstok).

## License

RollerworksSearch is released under the [MIT license](LICENSE).

The types and extensions are largely inspired on the Symfony Form Component, 
and contain a big amount of code from the Symfony project.

## Support

Use the issue tracker to create a new [support question](https://github.com/rollerworks/search/issues/new?labels=Question+%2F+Support&template=3_Support_question.md).

**Note:** Please be patient, it might take some time before your question is answered. **Do not ping the
maintainers.**

## Contributing

This is an open source project. If you'd like to contribute,
please read the [Contributing Guidelines][2]. If you're submitting
a pull request, please follow the guidelines in the [Submitting a Patch][3] section.

**Note:** RollerworksSearch is developed in a monolith repository, do not open pull request
against repositories marked as `[READ-ONLY]`, thank you.

[1]: https://getcomposer.org/doc/00-intro.md
[2]: https://github.com/rollerworks/contributing
[3]: https://contributing.readthedocs.org/en/latest/code/patches.html
[4]: http://rollerworkssearch.readthedocs.org/en/latest/
