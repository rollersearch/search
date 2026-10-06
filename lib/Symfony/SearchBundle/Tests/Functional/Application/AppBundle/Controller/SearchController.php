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

namespace Rollerworks\Bundle\SearchBundle\Tests\Functional\Application\AppBundle\Controller;

use Rollerworks\Bundle\SearchBundle\Tests\Fixtures\FieldSet\UserFieldSet;
use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\Exception\InvalidSearchConditionException;
use Rollerworks\Component\Search\Input\ProcessorConfig;
use Rollerworks\Component\Search\Loader\InputProcessorLoader;
use Rollerworks\Component\Search\SearchFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SearchController
{
    public function __construct(
        private readonly SearchFactory $searchFactory,
        private readonly InputProcessorLoader $inputProcessorLoader,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(Request $request)
    {
        if ($request->isMethod('POST')) {
            return new RedirectResponse($this->urlGenerator->generate('search', ['search' => $request->request->get('search', '')]));
        }

        try {
            $inputProcessor = $this->inputProcessorLoader->get('string_query');
            $processorConfig = new ProcessorConfig($this->searchFactory->createFieldSet(UserFieldSet::class));

            $condition = $inputProcessor->process($processorConfig, $query = $request->query->get('search', ''));

            if ($condition->isEmpty()) {
                return new Response('VALID: EMPTY');
            }

            return new Response('VALID: ' . ($query ?: 'EMPTY'));
        } catch (InvalidSearchConditionException $e) {
            return new Response(
                'INVALID: <ul>' . implode(
                    "\n",
                    array_map(
                        static fn (ConditionErrorMessage $e): string => '<li>' . $e->message . '</li>',
                        $e->getErrors()
                    )
                ) . '</ul>',
                500
            );
        }
    }
}
