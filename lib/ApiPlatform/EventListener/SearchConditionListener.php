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

namespace Rollerworks\Component\Search\ApiPlatform\EventListener;

use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Rollerworks\Component\Search\ApiPlatform\Exception\InvalidConditionException;
use Rollerworks\Component\Search\ApiPlatform\SearchConditionEvent;
use Rollerworks\Component\Search\ErrorList;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Rollerworks\Component\Search\Exception\UnexpectedTypeException;
use Rollerworks\Component\Search\Input\CachingInputProcessor;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\Loader\InputProcessorLoader;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * SearchConditionListener handles search conditions provided with the Request query.
 *
 * The condition is expected to be provided as an JsonInput or NormStringQuery format at `search`.
 * After this the Request attribute `_api_search_condition` is set with SearchCondition object.
 *
 * Note: Processing Exceptions are not handled by this listener, but handled by the Exception
 * Listener of the ApiPlatform.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class SearchConditionListener
{
    public function __construct(
        private readonly SearchFactory $searchFactory,
        private readonly InputProcessorLoader $inputProcessorLoader,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?CacheInterface $cache = null,
    ) {}

    /**
     * Listener callback.
     *
     * This listener is expected to be run after the Api EntryPoint but before ReadListener
     * on the kernel.request event.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $operation = $this->initializeOperation($request);

        if ($operation === null || ! $request->isMethodCacheable() || ! ($operation->canRead() ?? true)) {
            return;
        }

        $resourceClass = $operation->getClass();
        $extraProperties = $operation->getExtraProperties();
        $searchConfig = $extraProperties['rollerworks_search'] ?? [];

        if (empty($searchConfig)) {
            return;
        }

        $searchConfig = $this->resolveSearchConfiguration($searchConfig, $resourceClass, $request);

        $config = new ProcessorConfig($this->searchFactory->createFieldSet($searchConfig['fieldset']));
        $this->configureProcessor($config, $searchConfig, $resourceClass);

        $condition = $this->getCondition($request, $config);

        $conditionEvent = new SearchConditionEvent($condition, $resourceClass, $request);

        // First Dispatch a specific event to for this resource-class and then a generic one for ease of listening.
        // Note. If propagation is stopped for specific listener the generic listener is ignored.
        $this->eventDispatcher->dispatch($conditionEvent, SearchConditionEvent::SEARCH_CONDITION_EVENT . $resourceClass);
        $this->eventDispatcher->dispatch($conditionEvent, SearchConditionEvent::SEARCH_CONDITION_EVENT);

        $request->attributes->set('_api_search_condition', $condition);
    }

    private function initializeOperation(Request $request): ?HttpOperation
    {
        if ($request->attributes->get('_api_operation')) {
            return $request->attributes->get('_api_operation');
        }

        if ($request->attributes->get('_api_resource_class') === null) {
            return null;
        }

        $operationName = $request->attributes->get('_api_operation_name');
        /** @var HttpOperation $operation */
        $operation = $this->resourceMetadataCollectionFactory->create($request->attributes->get('_api_resource_class'))->getOperation($operationName);
        $request->attributes->set('_api_operation', $operation);

        return $operation;
    }

    private function resolveSearchConfiguration(array $searchConfig, string $resourceClass, Request $request): array
    {
        if (empty($searchConfig['contexts'])) {
            throw new RuntimeException(
                \sprintf(
                    'Resource "%s" is missing a contexts array. Add a "contexts" array with at least one entry.',
                    $resourceClass . '#attributes[rollerworks_search]'
                )
            );
        }

        $context = $request->attributes->get('_api_search_context', '_any');

        if (! isset($searchConfig['contexts'][$context])) {
            throw new RuntimeException(
                \sprintf(
                    'Search context "%s" is not supported for Resource "%s", supported: "%s".',
                    $context,
                    $resourceClass . '#attributes[rollerworks_search][contexts]',
                    implode('", "', array_keys($searchConfig['contexts']))
                )
            );
        }

        if (empty($searchConfig['contexts'][$context]) || empty($searchConfig['contexts'][$context]['fieldset'])) {
            throw new RuntimeException(
                \sprintf(
                    'Search context "%s" is incorrectly configured for Resource "%s", missing a "fieldset" reference.',
                    $context,
                    $resourceClass . '#attributes[rollerworks_search]'
                )
            );
        }

        $request->attributes->set('_api_search_context', $context);
        $request->attributes->set('_api_search_config', $searchConfig['contexts'][$context]);

        return $searchConfig['contexts'][$context];
    }

    private function configureProcessor(ProcessorConfig $config, array $options, string $resourceClass): void
    {
        if (empty($options['processor'])) {
            return;
        }

        foreach ($options['processor'] as $option => $value) {
            $method = 'set' . ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $option))));

            if (! method_exists($config, $method)) {
                throw new RuntimeException(\sprintf('Processor option "%s" is not supported for Resource "%s".', $option, $resourceClass));
            }

            if (\is_scalar($value) && ctype_digit((string) $value)) {
                $value = (int) $value;
            }

            $config->{$method}($value);
        }
    }

    private function getCondition(Request $request, ProcessorConfig $config): SearchCondition
    {
        $input = $request->query->get('search', '');

        if (! \is_string($input)) {
            throw new UnexpectedTypeException($input, 'string');
        }

        $format = ($input[0] ?? 'n') === '{' ? 'json' : 'norm_string_query';

        $inputProcessor = $this->inputProcessorLoader->get($format);

        if ($this->cache !== null && ($ttl = $config->getCacheTTL()) !== null) {
            $inputProcessor = new CachingInputProcessor(
                $this->cache,
                $this->searchFactory->getSerializer(),
                $inputProcessor,
                $ttl
            );
        }

        try {
            return $inputProcessor->process($config, $input);
        } catch (InvalidSearchConditionException $e) {
            $error = $e->getErrors();

            if (\is_array($error)) {
                $error = new ErrorList($error);
            }

            throw new InvalidConditionException($error, 0, $e);
        }
    }
}
