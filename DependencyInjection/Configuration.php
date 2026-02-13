<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection;

use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

readonly class Configuration implements ConfigurationInterface
{
    public function __construct(private array $resolversFactories, private array $loadersFactories)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('chamber_orchestra_image');
        $node = $builder->getRootNode();

        $node
            ->append($this->addResolversSections())
            ->append($this->addLoadersSections())
            ->beforeNormalization()
            ->ifTrue(function ($v) {
                return
                    empty($v['loaders'])
                    || empty($v['resolvers']);
            })
            ->then(function ($v) {
                if (empty($v['loaders'])) {
                    $v['loaders'] = [];
                }

                if (!\is_array($v['loaders'])) {
                    throw new LogicException('Loaders has to be array');
                }

                if (!\array_key_exists('default', $v['loaders'])) {
                    $v['loaders']['default'] = ['type' => 'filesystem'];
                }

                if (empty($v['resolvers'])) {
                    $v['resolvers'] = [];
                }

                if (!\is_array($v['resolvers'])) {
                    throw new LogicException('Resolvers has to be array');
                }

                if (!\array_key_exists('default', $v['resolvers'])) {
                    $v['resolvers']['default'] = ['type' => 'web_path'];
                }

                return $v;
            })
            ->end();

        $node
            ->children()
            ->scalarNode('driver')->defaultValue('Imagine\Imagick\Imagine')
            ->validate()
            ->ifTrue(function ($v) {
                return !\in_array($v, ['Imagine\Gd\Imagine', 'Imagine\Imagick\Imagine', 'Imagine\Gmagick\Imagine']);
            })
            ->thenInvalid('Invalid imagine driver specified: %s')
            ->end()
            ->end()
            ->scalarNode('resolver')->defaultValue('default')->end()
            ->scalarNode('loader')->defaultValue('default')->end()
            ->scalarNode('default_image')->defaultNull()->end()
            ->arrayNode('filters')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->children()
            ->scalarNode('resolver')->defaultNull()->end()
            ->scalarNode('loader')->defaultNull()->end()
            ->arrayNode('output')
            ->ignoreExtraKeys(false)
            ->children()
            ->scalarNode('quality')->defaultValue(75)->end()
            ->scalarNode('jpeg_quality')->defaultNull()->end()
            ->scalarNode('png_compression_level')->defaultNull()->end()
            ->scalarNode('png_compression_filter')->defaultNull()->end()
            ->scalarNode('webp_quality')->defaultNull()->end()
            ->booleanNode('optimize')->defaultFalse()->end()
            ->booleanNode('flatten')->defaultTrue()->end()
            ->scalarNode('format')->defaultNull()->end()
            ->booleanNode('animated')->defaultFalse()->end()
            ->end()
            ->end()
            ->scalarNode('default_image')->defaultNull()->end()
            ->arrayNode('processors')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->useAttributeAsKey('name')
            ->prototype('variable')->end()
            ->end()
            ->end()
            ->arrayNode('post_processors')
            ->defaultValue([])
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->useAttributeAsKey('name')
            ->prototype('variable')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $builder;
    }

    private function addResolversSections(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('resolvers');
        $node = $builder->getRootNode();
        $self = $this;

        $normalization = function ($conf) use ($self, $builder) {
            $conf = [
                'type' => 'custom',
                'service' => $conf,
            ];

            return $conf;
        };

        $children = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->beforeNormalization()
            ->ifTrue(function ($v) use ($self, $builder) {
                return \is_string($v);
            })
            ->then($normalization)
            ->end()
            ->children()
            ->enumNode('type')
            ->isRequired()
            ->cannotBeEmpty()
            ->values($this->getResolverNames())
            ->end()
            ->scalarNode('service')->defaultNull()->end()
            ->end();

        foreach ($this->resolversFactories as $factory) {
            $factory->addConfiguration($children);
        }

        return $node;
    }

    private function addLoadersSections(): NodeDefinition|ArrayNodeDefinition
    {
        $builder = new TreeBuilder('loaders');
        $node = $builder->getRootNode();

        $children = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('type')->cannotBeEmpty()->end()
            ->end();

        foreach ($this->loadersFactories as $factory) {
            $factory->addConfiguration($children);
        }

        return $node;
    }

    private function getResolverNames(): array
    {
        $names = \array_values(\array_map(function (ResolverFactoryInterface $factory) {
            return $factory->getName();
        }, $this->resolversFactories));

        $names[] = 'custom';

        return $names;
    }
}
