<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle;

use ChamberOrchestra\ImageBundle\DependencyInjection\ChamberOrchestraImageExtension;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\FileSystemLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\S3LoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\StreamLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\CustomResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\S3ResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\WebPathResolverFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ChamberOrchestraImageBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var ChamberOrchestraImageExtension $extension */
        $extension = $container->getExtension('chamber_orchestra_image');
        $extension->addResolverFactory(new WebPathResolverFactory());
        $extension->addResolverFactory(new CustomResolverFactory());
        $extension->addResolverFactory(new S3ResolverFactory());

        $extension->addLoaderFactory(new StreamLoaderFactory());
        $extension->addLoaderFactory(new FileSystemLoaderFactory());
        $extension->addLoaderFactory(new S3LoaderFactory());
    }
}
