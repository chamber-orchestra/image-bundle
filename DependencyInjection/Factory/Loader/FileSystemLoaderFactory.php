<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\FileSystemLoader;
use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FileSystemLoaderFactory extends AbstractLoaderFactory implements LoaderFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(FileSystemLoader::class);
        $definition->setArgument('$locator', new Reference($config['locator']));
        $definition->setArgument('$dataRoots', $this->resolveDataRoots($config['data_root'], $config['bundle_resources'], $container));

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'filesystem';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
            ->scalarNode('type')->defaultValue($this->getName())->cannotBeEmpty()->end()
            ->enumNode('locator')
            ->values(['ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemInsecureLocator', 'ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator'])
            ->info('Using the "filesystem_insecure" locator is not recommended due to a less secure resolver mechanism, but is provided for those using heavily symlinked projects.')
            ->defaultValue('ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator')
            ->end()
            ->arrayNode('data_root')
            ->beforeNormalization()
            ->ifString()
            ->then(function ($value) {
                return [$value];
            })
            ->end()
            ->treatNullLike([])
            ->treatFalseLike([])
            ->defaultValue(['%kernel.project_dir%/public'])
            ->prototype('scalar')
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->arrayNode('bundle_resources')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->enumNode('access_control_type')
            ->values(['blacklist', 'whitelist'])
            ->info('Sets the access control method applied to bundle names in "access_control_list" into a blacklist or whitelist.')
            ->defaultValue('blacklist')
            ->end()
            ->arrayNode('access_control_list')
            ->defaultValue([])
            ->prototype('scalar')
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();
    }

    private function resolveDataRoots(array $staticPaths, array $config, ContainerBuilder $container): array
    {
        if (false === $config['enabled']) {
            return $staticPaths;
        }

        $resourcePaths = [];

        foreach ($this->getBundleResourcePaths($container) as $name => $path) {
            if (('whitelist' === $config['access_control_type']) === \in_array($name, $config['access_control_list']) && \is_dir($path)) {
                $resourcePaths[$name] = $path;
            }
        }

        return \array_merge($staticPaths, $resourcePaths);
    }

    private function getBundleResourcePaths(ContainerBuilder $container): array
    {
        if ($container->hasParameter('kernel.bundles_metadata')) {
            $paths = $this->getBundlePathsUsingMetadata($container->getParameter('kernel.bundles_metadata'));
        } else {
            $paths = $this->getBundlePathsUsingNamedObj($container->getParameter('kernel.bundles'));
        }

        return \array_map(function ($path) {
            return $path.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'public';
        }, $paths);
    }

    private function getBundlePathsUsingMetadata(array $metadata): array
    {
        return \array_combine(array_keys($metadata), \array_map(function ($data) {
            return $data['path'];
        }, $metadata));
    }

    private function getBundlePathsUsingNamedObj(array $classes): array
    {
        $paths = [];

        foreach ($classes as $c) {
            try {
                $r = new \ReflectionClass($c);
            } catch (\ReflectionException $exception) {
                throw new InvalidArgumentException(\sprintf('Unable to resolve bundle "%s" while auto-registering bundle resource paths.', $c), null, $exception);
            }

            $paths[$r->getShortName()] = \dirname($r->getFileName());
        }

        return $paths;
    }
}
