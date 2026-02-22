<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ChamberOrchestra\ImageBundle\Binary\BinaryMimeTypeGuesser;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\AvifPostProcessor;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessageHandler;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\CwebpPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\MozJpegPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PngquantPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PostProcessorInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\ProcessorInterface;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\ImagineInterface;
use Imagine\Imagick\Imagine as ImagickImagine;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('chamber_orchestra_image.cache', '')
        ->set('chamber_orchestra_image.pngquant.binary', '/usr/bin/pngquant')
        ->set('chamber_orchestra_image.mozjpeg.binary', '/opt/mozjpeg/bin/cjpeg')
        ->set('chamber_orchestra_image.cwebp.binary', '/usr/local/bin/cwebp')
        ->set('chamber_orchestra_image.cwebp.lib', '/usr/local/lib')
        ->set('chamber_orchestra_image.avifenc.binary', '/usr/local/bin/avifenc')
    ;

    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private()
        ->bind('$globalDefaultImage', '%chamber_orchestra_image.default_image%')
        ->bind('$cachePrefix', '%chamber_orchestra_image.cache_prefix%')
        ->bind('$mimeTypes', service('mime_types'))
        ->bind('$lockFactory', service(LockFactory::class)->nullOnInvalid())
    ;

    $services->instanceof(ProcessorInterface::class)
        ->tag('chamber_orchestra_image.filter.processor')
    ;

    $services->instanceof(PostProcessorInterface::class)
        ->tag('chamber_orchestra_image.filter.post_processor')
    ;

    // Preload drivers
    if (\extension_loaded('imagick')) {
        $services->set(ImagickImagine::class);
    }

    if (\extension_loaded('gd')) {
        $services->set(GdImagine::class);
    }

    if (\extension_loaded('gmagick')) {
        $services->set(GmagickImagine::class);
    }
    $services->alias(ImagineInterface::class, 'chamber_orchestra_image.driver');

    $services->load('ChamberOrchestra\\ImageBundle\\', '../../')
        ->exclude('../../{DependencyInjection,Resources,Exception,EventSubscriber,Model,Service/FilterResult.php,Message/ProcessImageMessage.php,tests,vendor}')
    ;

    $services->set(BinaryMimeTypeGuesser::class)
        ->args([inline_service(FileinfoMimeTypeGuesser::class)])
    ;

    $services->load('ChamberOrchestra\\ImageBundle\\Controller\\', '../../Controller')
        ->public()
        ->tag('controller.service_arguments')
    ;

    $services->set(FilterManager::class)
        ->arg('$processorsLocator', tagged_locator('chamber_orchestra_image.filter.processor', defaultIndexMethod: 'getIndexName'))
        ->arg('$postProcessorsLocator', tagged_locator('chamber_orchestra_image.filter.post_processor', defaultIndexMethod: 'getIndexName'))
    ;

    $services->set(FilterConfiguration::class)
        ->arg('$filters', '%chamber_orchestra_image.filters%')
    ;

    $services->set(FilterService::class)
        ->arg('$messageBus', service(MessageBusInterface::class)->nullOnInvalid())
    ;

    $services->set(ProcessImageMessageHandler::class)
        ->arg('$concurrencyLimit', '%chamber_orchestra_image.concurrency%')
    ;

    $services->set(Signer::class);

    $services->set(PngquantPostProcessor::class)
        ->args(['%chamber_orchestra_image.pngquant.binary%'])
    ;

    $services->set(MozJpegPostProcessor::class)
        ->args(['%chamber_orchestra_image.mozjpeg.binary%'])
    ;

    $services->set(CwebpPostProcessor::class)
        ->args(['%chamber_orchestra_image.cwebp.binary%', '%chamber_orchestra_image.cwebp.lib%'])
    ;

    $services->set(AvifPostProcessor::class)
        ->args(['%chamber_orchestra_image.avifenc.binary%'])
    ;
};
