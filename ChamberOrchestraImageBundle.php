<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle;

use ChamberOrchestra\ImageBundle\DependencyInjection\ChamberOrchestraImageExtension;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\FileSystemLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\StreamLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\CustomResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\WebPathResolverFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ChamberOrchestraImageBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var $extension ChamberOrchestraImageExtension */
        $extension = $container->getExtension('chamber_orchestra_image');
        $extension->addResolverFactory(new WebPathResolverFactory());
        $extension->addResolverFactory(new CustomResolverFactory());

        $extension->addLoaderFactory(new StreamLoaderFactory());
        $extension->addLoaderFactory(new FileSystemLoaderFactory());
    }
}