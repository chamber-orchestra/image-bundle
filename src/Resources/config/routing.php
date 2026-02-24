<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader\Configurator;

use ChamberOrchestra\ImageBundle\Controller\ClientAction;
use ChamberOrchestra\ImageBundle\Controller\RuntimeAction;
use ChamberOrchestra\ImageBundle\Enum\ImageFormat;

return static function (RoutingConfigurator $routes): void {
    $routes->add('_chamber_orchestra_image_client', '%chamber_orchestra_image.cache_prefix%/{pathHash}/{optionsHash}/{name}@{density}x.{format}')
        ->controller(ClientAction::class)
        ->methods(['GET'])
        ->requirements([
            'pathHash' => '[A-Za-z0-9_-]{16}',
            'optionsHash' => '[A-Za-z0-9_-]{16}',
            'name' => '[A-Za-z0-9_-]+',
            'density' => '[1-4]',
            'format' => \implode('|', ImageFormat::values()),
        ])
    ;

    $routes->add('_chamber_orchestra_image', '/_media/{filter}/{hash}/{path}')
        ->controller(RuntimeAction::class)
        ->methods(['GET'])
        ->requirements([
            'filter' => '[A-Za-z0-9_\-]+',
            'path' => '.+',
            'hash' => '[A-Za-z0-9_-]{16}/[A-Za-z0-9_-]{16}',
        ])
    ;
};
