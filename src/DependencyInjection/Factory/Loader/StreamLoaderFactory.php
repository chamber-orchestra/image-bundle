<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\StreamLoader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class StreamLoaderFactory extends AbstractLoaderFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(StreamLoader::class);
        $definition->replaceArgument(0, $config['wrapper_prefix']);
        $definition->replaceArgument(1, $config['context']);
        $definition->replaceArgument(2, $config['allowed_schemes']);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    public function getName(): string
    {
        return 'stream';
    }

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
                ->arrayNode('allowed_schemes')
                    ->scalarPrototype()->end()
                    ->defaultValue(['file', 'data'])
                ->end()
            ->end()
        ;
    }
}
