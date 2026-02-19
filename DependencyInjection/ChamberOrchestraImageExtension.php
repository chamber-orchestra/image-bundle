<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection;

use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\LoaderFactoryInterface;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use ChamberOrchestra\ImageBundle\EventSubscriber\FileRemoveSubscriber;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\FileBundle\Events\PostRemoveEvent;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class ChamberOrchestraImageExtension extends ConfigurableExtension
{
    /**
     * @var ResolverFactoryInterface[]
     */
    protected array $resolversFactories = [];
    /**
     * @var LoaderFactoryInterface[]
     */
    protected array $loadersFactories = [];

    public function addResolverFactory(ResolverFactoryInterface $resolverFactory): void
    {
        $this->resolversFactories[$resolverFactory->getName()] = $resolverFactory;
    }

    public function addLoaderFactory(LoaderFactoryInterface $loaderFactory): void
    {
        $this->loadersFactories[$loaderFactory->getName()] = $loaderFactory;
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->resolversFactories, $this->loadersFactories);
    }

    /**
     * @throws Exception
     */
    protected function loadInternal(array $config, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $this->loadResolvers($config['resolvers'], $container);
        $this->loadLoaders($config['loaders'], $container);

        $container->setAlias('chamber_orchestra_image.driver', $config['driver']);

        $container->setParameter('chamber_orchestra_image.cache.resolver', $config['resolver']);
        $container->setParameter('chamber_orchestra_image.binary.loader', $config['loader']);

        $container->setParameter('chamber_orchestra_image.default_image', $config['default_image']);
        $container->setParameter('chamber_orchestra_image.filters', $config['filters']);

        if (\class_exists(PostRemoveEvent::class)) {
            $container->autowire(FileRemoveSubscriber::class)
                ->setAutoconfigured(true);
        }
    }

    protected function loadResolvers(array $config, ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CacheManager::class)) {
            return;
        }

        $definition = $container->getDefinition(CacheManager::class);

        foreach ($config as $name => $resolverConfig) {
            $type = $resolverConfig['type'];
            $factory = $this->resolversFactories[$type];
            $id = $factory->create($container, $name, $resolverConfig);

            $definition->addMethodCall('addResolver', [new Reference($id), $name]);
        }
    }

    protected function loadLoaders(array $config, ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DataManager::class)) {
            return;
        }

        $definition = $container->getDefinition(DataManager::class);

        foreach ($config as $name => $loaderConfig) {
            $factoryName = $loaderConfig['type'];
            $factory = $this->loadersFactories[$factoryName];
            $id = $factory->create($container, $name, $loaderConfig);

            $definition->addMethodCall('addLoader', [new Reference($id), $name]);
        }
    }
}
