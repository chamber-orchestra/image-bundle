<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\StreamLoader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class StreamLoaderFactory extends AbstractLoaderFactory
{
    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(StreamLoader::class);
        $definition->replaceArgument(0, $config['wrapper_prefix']);
        $definition->replaceArgument(1, $config['context']);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'stream';
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
            ->scalarNode('wrapper_prefix')
            ->defaultValue('')
            ->end()
            ->scalarNode('context')
            ->defaultValue(null)
            ->end()
            ->end();
    }
}
