<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WebPathResolverFactory extends AbstractResolverFactory implements ResolverFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(WebPathResolver::class);

        $definition
            ->setArgument('$webRootDir', $config['web_root'])
            ->setArgument('$cachePrefix', $config['cache_prefix']);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'web_path';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
            ->scalarNode('web_root')
            ->defaultValue('%kernel.project_dir%/public')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('cache_prefix')
            ->defaultValue('media')
            ->cannotBeEmpty()
            ->end()
            ->end();
    }
}
