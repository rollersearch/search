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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Rollerworks\Component\Search\Extension\Core\Type\SearchFieldType;
use Rollerworks\Component\Search\Extension\Symfony\Validator\InputValidator;
use Rollerworks\Component\Search\Extension\Symfony\Validator\Type\FieldTypeValidatorExtension;
use Rollerworks\Component\Search\Input\Validator\ChainValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('rollerworks_search.validator', ChainValidator::class)
        ->args([
            tagged_iterator('rollerworks_search.input_validator'),
        ])
    ;

    $services->set('rollerworks_search.validator.constraint', InputValidator::class)
        ->tag('rollerworks_search.input_validator')
        ->args([service('validator')])
    ;

    $services->set(FieldTypeValidatorExtension::class)
        ->tag('rollerworks_search.type_extension', ['extended_type' => SearchFieldType::class])
    ;
};
