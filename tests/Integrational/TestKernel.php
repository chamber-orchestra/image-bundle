<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational;

use ChamberOrchestra\ImageBundle\ChamberOrchestraImageBundle;
use ChamberOrchestra\ImageBundle\Serializer\Metadata\ImageFilterMetadataFactory;
use ChamberOrchestra\ImageBundle\Serializer\Normalizer\ImageFilterAttributeNormalizer;
use ChamberOrchestra\ViewBundle\ChamberOrchestraViewBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new ChamberOrchestraViewBundle(),
            new ChamberOrchestraImageBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test_secret_for_rehearsal',
            'test' => true,
            'serializer' => ['enabled' => true],
            'property_access' => ['enabled' => true],
        ]);

        $container->extension('chamber_orchestra_image', [
            'driver' => 'gd',
            'filters' => [
                'default' => [],
            ],
        ]);

        $container->extension('chamber_orchestra_view', []);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/src/Resources/config/routing.php');
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements \Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition(ImageFilterAttributeNormalizer::class)) {
                    $container->getDefinition(ImageFilterAttributeNormalizer::class)->setPublic(true);
                }

                if ($container->hasDefinition(ImageFilterMetadataFactory::class)) {
                    $container->getDefinition(ImageFilterMetadataFactory::class)->setPublic(true);
                }
            }
        });
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return \sys_get_temp_dir().'/chamber_orchestra_image_test/cache';
    }

    public function getLogDir(): string
    {
        return \sys_get_temp_dir().'/chamber_orchestra_image_test/log';
    }
}
