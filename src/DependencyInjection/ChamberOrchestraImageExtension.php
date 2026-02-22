<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection;

use ChamberOrchestra\FileBundle\Events\PostRemoveEvent;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\LoaderFactoryInterface;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use ChamberOrchestra\ImageBundle\EventSubscriber\FileRemoveSubscriber;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\CacheResolver;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class ChamberOrchestraImageExtension extends ConfigurableExtension
{
    /**
     * @var array<string, ResolverFactoryInterface>
     */
    protected array $resolversFactories = [];
    /**
     * @var array<string, LoaderFactoryInterface>
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

    /**
     * @param array<string, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->resolversFactories, $this->loadersFactories);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \Exception
     */
    protected function loadInternal(array $config, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        /** @var array<string, array<string, mixed>> $resolvers */
        $resolvers = $config['resolvers'];
        /** @var array<string, array<string, mixed>> $loaders */
        $loaders = $config['loaders'];

        /** @var array{enabled: bool|null, service: string, lifetime: int} $cacheConfig */
        $cacheConfig = $config['cache'];

        $this->loadResolvers($resolvers, $container, $cacheConfig);
        $this->loadLoaders($loaders, $container);

        /** @var string $driver */
        $driver = $config['driver'];
        $container->setAlias('chamber_orchestra_image.driver', $driver);

        /** @var string $resolver */
        $resolver = $config['resolver'];
        /** @var string $binaryLoader */
        $binaryLoader = $config['loader'];

        /** @var string|null $defaultImage */
        $defaultImage = $config['default_image'];
        $container->setParameter('chamber_orchestra_image.default_image', $defaultImage);
        /** @var string $cachePath */
        $cachePath = $config['cache_path'];
        $container->setParameter('chamber_orchestra_image.cache_path', $cachePath);
        /** @var string $cachePrefix */
        $cachePrefix = $config['cache_prefix'];
        $container->setParameter('chamber_orchestra_image.cache_prefix', $cachePrefix);
        /** @var int $concurrency */
        $concurrency = $config['concurrency'];
        $container->setParameter('chamber_orchestra_image.concurrency', $concurrency);

        /** @var array<string, array<string, mixed>> $filters */
        $filters = $config['filters'];

        $globalAsync = match ($config['async']) {
            'auto' => \class_exists(\Symfony\Component\Messenger\MessageBusInterface::class),
            true => true,
            default => false,
        };

        foreach ($filters as $name => &$filter) {
            if ($filter['exposed'] ?? false) {
                if (null === $filter['secret']) {
                    throw new InvalidConfigurationException(\sprintf('Filter "%s" is exposed and requires an explicit "secret" different from the application secret.', $name));
                }
            }

            $filter['secret'] ??= '%kernel.secret%';
            $filter['resolver'] ??= $resolver;
            $filter['loader'] ??= $binaryLoader;
            $filter['processors'] ??= [];
            $filter['post_processors'] ??= [];
            $filter['async'] = $filter['async'] ?? $globalAsync;
            $filter['exposed'] ??= false;

            if ($filter['exposed'] && $filter['secret'] === $container->getParameter('kernel.secret')) {
                throw new InvalidConfigurationException(\sprintf('Filter "%s" is exposed and its "secret" must differ from the application secret.', $name));
            }
        }
        unset($filter);

        $container->setParameter('chamber_orchestra_image.filters', $filters);

        if (\class_exists(PostRemoveEvent::class)) {
            $container->autowire(FileRemoveSubscriber::class)
                ->setAutoconfigured(true);
        }
    }

    /**
     * @param array<string, array<string, mixed>>                       $config
     * @param array{enabled: bool|null, service: string, lifetime: int} $cacheConfig
     */
    protected function loadResolvers(array $config, ContainerBuilder $container, array $cacheConfig): void
    {
        if (!$container->hasDefinition(CacheManager::class)) {
            return;
        }

        $cacheEnabled = $cacheConfig['enabled'] ?? !$container->getParameter('kernel.debug');
        $definition = $container->getDefinition(CacheManager::class);

        foreach ($config as $name => $resolverConfig) {
            /** @var string $type */
            $type = $resolverConfig['type'];
            $factory = $this->resolversFactories[$type];
            $innerResolverId = $factory->create($container, $name, $resolverConfig);

            if ($cacheEnabled) {
                $cachedId = 'chamber_orchestra_image.cache.resolver.'.$name.'.cached';
                $container->setDefinition($cachedId, new Definition(CacheResolver::class, [
                    new Reference($cacheConfig['service']),
                    new Reference($innerResolverId),
                    ['lifetime' => $cacheConfig['lifetime']],
                ]));

                $definition->addMethodCall('addResolver', [new Reference($cachedId), $name]);
            } else {
                $definition->addMethodCall('addResolver', [new Reference($innerResolverId), $name]);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    protected function loadLoaders(array $config, ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DataManager::class)) {
            return;
        }

        $definition = $container->getDefinition(DataManager::class);

        foreach ($config as $name => $loaderConfig) {
            /** @var string $factoryName */
            $factoryName = $loaderConfig['type'];
            $factory = $this->loadersFactories[$factoryName];
            $id = $factory->create($container, $name, $loaderConfig);

            $definition->addMethodCall('addLoader', [new Reference($id), $name]);
        }
    }
}
