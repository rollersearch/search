UPGRADE FROM 2.0 to 2.1
=======================

### Core

 * The `ValueHolder` getters are deprecated, access their public properties directly instead.
   The `ValueHolder` getters will be removed in v3.0.

 * Not implementing the `initialize()` method in a `Rollerworks\Component\Search\Input\Validator`
   implementation class is deprecated and will be required in v3.0.

### Symfony Bundle

 * The `rollerworks_search.validator` service class has been replaced with the
  `Rollerworks\Component\Search\Input\Validator\ChainValidator`.

  Tag a input-validator service as `rollerworks_search.input_validator` to add
  it to the chain of validators.

UPGRADE FROM 2.0-BETA13 to 2.0-BETA14
=====================================

### Core

 * The `SearchOrder` now expects an associative array of field-names and direction.
   Passing a `ValuesGroup` is deprecated, and will be removed in v3.0.

UPGRADE FROM 2.0-BETA10 to 2.0-BETA13
=====================================

### Core

  * The `Rollerworks\Component\Search\Extension\Core\DataTransformer\IntegerToStringTransformer`
    deprecated usage of accepting a `null` value for `$roundingMode`, use `IntegerToStringTransformer::ROUND_DOWN` instead.

  * The `Rollerworks\Component\Search\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer`
    deprecated usage of accepting a `null` value for `$grouping` and `$roundingMode`
    use `NumberToLocalizedStringTransformer::ROUND_DOWN` for `$roundingMode` and `false` for `$grouping` instead.

UPGRADE FROM 2.0-BETA9 to 2.0-BETA10
====================================

### Api-Platform

 * Support for Api-Platform 2.4 was dropped, you need at least 4.2.
   Older beta packages can be used if support is required.

UPGRADE FROM 2.0-BETA6 to 2.0-BETA9
===================================

### Doctrine DBAL/ORM

 * Passing `null` as type to `ConversionHints::createParamReferenceFor()` is deprecated 
   and will no longer be accepted in v3.0.0, pass a type name instead. 

   For now passing `null` defaults to "string", and might fail under specific conditions.

UPGRADE FROM 2.0-BETA5 to 2.0-BETA6
===================================

 * The Symfony Input Validator no longer validates a `PatternMatch` value type.
   Set the `pattern_match_constraints` option to validate this specific type, with
   it's own constraints.

   **Note:** A PatternMatch will likely not contain a full value, for more advanced
   validating it's best to create your own input validator.

UPGRADE FROM 2.0-BETA4 to 2.0-BETA5
===================================

 * Translation ids have been changed to better fit there context, see translation in
   "lib/Core/Resources/translations" for new ids.

UPGRADE FROM 2.0-BETA2 to 2.0-BETA3
===================================

 * Support for PHP < 8.1 was dropped.

 * Support for Symfony 5 was dropped.

 * Support for Api-Platform 2.4 was dropped, 2.0-BETA2 of the components 
   still supports all newer versions of RollerworksSearch.

### Doctrine DBAL 

  * Support for using a query as string was removed, a DBAL QueryBuilder
    is now required to be passed to the generator.

  * The `DoctrineDbalFactory::createCachedConditionGenerator()` method now 
    requires a DBAL QueryBuilder and SearchCondition is provided instead
    of a ``ConditionGenerator`` instance.
 
  * The `ConditionGenerator` now longer provides access to the generated
    condition and parameters. These are applied automatically when calling `apply()`.

    ```php
    // Doctrine\DBAL\Query\QueryBuilder object
    $qb = $connection->createQueryBuilder();

    // Rollerworks\Component\Search\SearchCondition object
    $searchCondition = ...;

    $conditionGenerator = $doctrineDbalFactory->createConditionGenerator($qb, $searchCondition);

    // Set fields mapping
    // ....

    // Apply the condition (with ordering, if any) to the QueryBuilder
    $conditionGenerator->apply();

    // Get all the records
    // See http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/data-retrieval-and-manipulation.html#data-retrieval
    $result = $qb->execute();
    ````
    
### Doctrine DBAL/ORM

 * Doctrine mapping type as object is deprecated and will no longer work in 3.0,
   use a type-name as string instead.

UPGRADE FROM 2.0-BETA1 to 2.0-BETA2
===================================

 * Support for PHP < 7.4 was dropped.
 
 * Support for Symfony 4 was dropped.

 * Support for PHPUnit < 9.5 was dropped.

### Elasticsearch

 * Support for Elastica 6 was dropped.

 * Support for Elasticsearch 6 was dropped.

   _See the upgrade instructions of Elasticsearch itself for more information._

### Doctrine ORM

 * Support for passing a `Doctrine\ORM\Query` object in the generators was removed, 
   pass a `Doctrine\ORM\QueryBuilder` object instead.
   
  _This BC change was required to make applying of result-ordering possible without worrying
   to much about details and edge-cases._

 * The methods `getWhereClause()` and `getParameters()` on the ConditionGenerators were removed.
   _It's still possible to generate a stand-alone where-clause by using the `DqlConditionGenerator` directly, 
    but this is not officially supported nor documented._

 * The `createCachedConditionGenerator` of `DoctrineOrmFactory` now expects a
   a `QueryBuilder` and `SearchCondition` are provided instead of a ConditionGenerator.

   Before:
   
      ```php
      $generator = $ormFactory->createConditionGenerator($query, $searchCondition);
      $generator = $ormFactory->createCachedConditionGenerator($generator, 60 * 60);
      ```
   
   Now:
   
      ```php
      $generator = $ormFactory->createCachedConditionGenerator($query, $searchCondition, 60 * 60);
      ```
   
 * The `updateQuery()` method on the ConditionGenerators was renamed to `apply()` and no 
   longer supports a prepend for the query, as the query must now always be a `QueryBuilder`.

UPGRADE FROM 2.0-ALPHA23 to 2.0-ALPHA24
=======================================

 * The `html5` option for the `DateTimeType` has been removed.
   Only the RFC3339 for the norm-input format is supported now.

UPGRADE FROM 2.0-ALPHA21 to 2.0-ALPHA23
=======================================

 * The `$forceNew` argument in `SearchConditionBuilder::field()` is deprecated and will
   be removed in v2.0.0-BETA1, use `overwriteField()` instead.
    
### Doctrine DBAL

 * Support for SQLite was removed in Doctrine DBAL.

 * Values are no longer embedded but are now provided as parameters,
   make sure to bind these before executing the query.
   
   Before:
   
   ```php
   $whereClause = $conditionGenerator->getWhereClause();
   $statement = $connection->execute('SELECT * FROM tableName '.$whereClause);
   
   $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
   ```
   
   Now:
   
   ```php
   $whereClause = $conditionGenerator->getWhereClause();
   $statement = $connection->prepare('SELECT * FROM tableName '.$whereClause);
   
   $conditionGenerator->bindParameters($statement);

   $statement->execute();
   
   $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
   ```
   
 * The `Rollerworks\Component\Search\Doctrine\Dbal\ValueConversion::convertValue()` method
   now expects a `string` type is returned, and requires a return-type.
   
 * Conversion strategies was changed to return a different column/value
   statement rather than keeping all strategies cached.
   
   Use the `ConversionHint` new parameters and helper method to determine
   the value for the Column.

### Doctrine ORM
   
 * Support for Doctrine ORM NativeQuery was removed, use the Doctrine DBAL
   condition-generator instead for this usage.
    
 * Values are no longer embedded but are now provided as parameters,
   make sure to bind these before executing the query.
   
   Note: Using the `updateQuery()` method already performs the binding process.
   
 * Doctrine DBAL conversions are no longer applied, instead the Doctrine ORM
   integration now has it's own conversion API with a much more powerful integration.
   
   **Note:** Any functions used in the conversion-generated DQL must be registered
   with the EntityManager configuration, refer to the Doctrine ORM manual for details. 
   

UPGRADE FROM 2.0-ALPHA19 to 2.0-ALPHA20
=======================================

 * The DataTransformers have been synchronized with the Symfony
   versions, which might cause some minor BC breakages.
   
   * The `BaseNumberTransformer` has been removed, 
     extend from `NumberToLocalizedStringTransformer` instead.
   * The `pattern` option of `DateTimeType` now only affects the
     view transformer, the norm transformer will use either the `DateTimeToRfc3339Transformer`
     or `DateTimeToHtml5LocalDateTimeTransformer` when the `html5` option is set to true.
   * The `precision` option of the `NumberType` has been renamed to `scale`.
   * The `IntegerType` no longer accepts float values.


UPGRADE FROM 2.0-ALPHA12 to 2.0-ALPHA13
=======================================

 * The ArrayInput processor has been removed.
 
 * ApiPlatform SearchConditionListener no longer supports array-input. 
   Use JSON or the NormStringQuery input-format instead.
   
 * The default restriction values of `ProcessorConfig` have been changed
   to provide a better default;
   
   * Maximum values per field is now 100 (was 1000)
   * Maximum number of groups is now 10 (was 100)
   * Nesting is now 5 (was 100)
   
   Unless you must support a higher number of values 
   it is advised to not increase these values.

UPGRADE FROM 2.0-ALPHA8 to 2.0-ALPHA12
======================================

### Core

 * The ConditionOptimizers have been removed.
 
 * The XmlInput processor has been removed.

### Processor

 * The SearchProcessor Component has been removed, use an InputProcessor directly.
   
   **Before:**
   
   ```php
   $inputProcessorLoader = Loader\InputProcessorLoader::create();
   $conditionExporterLoader = Loader\ConditionExporterLoader::create();    
   $processor = new Psr7SearchProcessor($searchFactory, $inputProcessorLoader, $conditionExporterLoader);
   
   $request = ...; // A PSR-7 ServerRequestInterface object instance
   
   $processorConfig = new ProcessorConfig($userFieldSet);
   $searchPayload = $processor->processRequest($request, $processorConfig);
   
   if ($searchPayload->isChanged() && $searchPayload->isValid()) {
       header('Location: /search?search='.$searchPayload->searchCode);
       exit();
   }
   
   if (!$payload->isValid()) {
       foreach ($payload->messages as $error) {
          echo (string) $error.PHP_EOL;
       }
   }
   
   // Notice: This is null when there are errors, when the condition is valid but has
   // no fields/values this is an empty SearchCondition object.
   $condition = $payload->searchCondition;
   ```
   
   **After:**
   
   ```php
   // ...
   
   $inputProcessor = new StringQueryInput(); // Can be wrapped in a CachingInputProcessor
   $processorConfig = new ProcessorConfig($fieldSet);
   
   $request = ...; // A PSR-7 ServerRequestInterface object instance
   
   try {
       if ($request->getMethod() === 'POST') {
           $query = $request->getQueryParams()['search'] ?? '';
           
           header('Location: /search?search='.$searchPayload->searchCode);
           exit();
           
           // return new RedirectResponse($request->getRequestUri().'?search='.$query);
       }
       
       $query = $request->getParsedBody()['search'] ?? '';
       $condition = $inputProcessor->process($processorConfig, $query);
       
       // Use condition
   } catch (InvalidSearchConditionException $e) {
       foreach ($e->getErrors() as $error) {
          echo (string) $error.PHP_EOL;
       }
   }
   ```
   
   **Note:** The ArrayInput processor has been removed, only string-type input
   formats (StringInput and JsonInput) are supported now.

### ApiPlatform

 * The `ApiSearchProcessor` has been removed. Internally the `SearchConditionListener`
   now handles the user-input and error handling.
 
 * The `SearchConditionListener` constructor has changed:
 
    **Before:**
 
    ```
    SearchFactory $searchFactory
    SearchProcessor $searchProcessor
    UrlGeneratorInterface $urlGenerator
    ResourceMetadataFactory $resourceMetadataFactory
    EventDispatcherInterface $eventDispatcher
    ```
   
    **After:**
   
    ``` 
    SearchFactory $searchFactory
    InputProcessorLoader $inputProcessorLoader
    ResourceMetadataFactory $resourceMetadataFactory
    EventDispatcherInterface $eventDispatcher
    CacheInterface $cache = null
    ```
    
    **Note:** The `$cache` argument is optional and only used when the `$cacheTTL`
    of the `ProcessorConfig` is configured.
    
 * Cache TTL configuration has been moved to `Rollerworks\Component\Search\Input\ProcessorConfig`, 
   the metadata configuration format has remained unchanged.
 
 * The Input format is now automatically detected by the first character.
   When the provided input starts with an `{` the `JsonInput` processor is used,
   otherwise the `NormStringQueryInput` processor is used.
   
 * ArrayInput is deprecated and is internally delegated to the JsonInputProcessor.
   
   **In RollerworksSearch v2.0.0-ALPHA12 support for ArrayInput is completely 
   removed and will throw an exception instead.**

UPGRADE FROM 2.0-ALPHA5 to 2.0-ALPHA8
=====================================

## ApiPlatform

* The `Rollerworks\Component\Search\ApiPlatform\EventListener\SearchConditionListener`
  now requires an `EventDispatchInterface` instance as last argument.
  
## Doctrine DBAL

* The `Rollerworks\Component\Search\Doctrine\Dbal\StrategySupportedConversion::getConversionStrategy`
  method must now return an integer (and is enforced with a return-type).

UPGRADE FROM 2.0-ALPHA2 to 2.0-ALPHA5
=====================================

* Support for using Regex in ValueMatch has been removed.
  
  * The constants `PatternMatch::PATTERN_REGEX` and `PatternMatch::PATTERN_NOT_REGEX`
    have been removed.

  * The method `PatternMatch::isRegex` has been removed.

UPGRADE FROM 2.0-ALPHA1 to 2.0-ALPHA2
=====================================

* The `ValueComparison` namespaces and classes have been renamed to `ValueComparator`

* The `FieldConfig::setValueComparison` method has been renamed to `setValueComparator`

* The `FieldConfig::getValueComparison` method has been renamed to `getValueComparator`
