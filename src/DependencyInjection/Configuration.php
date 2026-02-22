<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection;

use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\ResolverFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final readonly class Configuration implements ConfigurationInterface
{
    /**
     * @param array<string, ResolverFactoryInterface>              $resolversFactories
     * @param array<string, Factory\Loader\LoaderFactoryInterface> $loadersFactories
     */
    public function __construct(private array $resolversFactories, private array $loadersFactories)
    {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('chamber_orchestra_image');
        $node = $builder->getRootNode();

        $node
            ->append($this->addResolversSections())
            ->append($this->addLoadersSections())
            ->beforeNormalization()
            ->ifTrue(function (mixed $v): bool {
                \assert(\is_array($v));

                return
                    (!isset($v['loaders']) || [] === $v['loaders'])
                    || (!isset($v['resolvers']) || [] === $v['resolvers']);
            })
            ->then(function (mixed $v): mixed {
                /** @var array<string, mixed> $v */
                if (empty($v['loaders'])) {
                    $v['loaders'] = [];
                }

                if (!\is_array($v['loaders'])) {
                    throw new \LogicException('Loaders has to be array');
                }

                if (!\array_key_exists('default', $v['loaders'])) {
                    $v['loaders']['default'] = ['type' => 'filesystem'];
                }

                if (empty($v['resolvers'])) {
                    $v['resolvers'] = [];
                }

                if (!\is_array($v['resolvers'])) {
                    throw new \LogicException('Resolvers has to be array');
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
                            return !\in_array($v, ['Imagine\Gd\Imagine', 'Imagine\Imagick\Imagine', 'Imagine\Gmagick\Imagine'], true);
                        })
                        ->thenInvalid('Invalid imagine driver specified: %s')
                    ->end()
                ->end()
                ->scalarNode('resolver')->defaultValue('default')->end()
                ->scalarNode('loader')->defaultValue('default')->end()
                ->scalarNode('default_image')->defaultNull()->end()
                ->scalarNode('cache_path')->defaultValue('%kernel.project_dir%/public/media')->cannotBeEmpty()->end()
                ->scalarNode('cache_prefix')->defaultValue('/media')->cannotBeEmpty()->end()
                ->scalarNode('async')
                    ->defaultValue('auto')
                    ->validate()
                        ->ifNotInArray([true, false, 'auto'])
                        ->thenInvalid('The "async" option must be true, false, or "auto" (auto-detect Symfony Messenger).')
                    ->end()
                ->end()
                ->integerNode('concurrency')->defaultValue(0)->min(0)->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->scalarNode('service')->defaultValue('cache.app')->end()
                        ->integerNode('lifetime')->defaultValue(3600)->end()
                    ->end()
                ->end()
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
                                    ->scalarNode('avif_quality')->defaultNull()->end()
                                    ->booleanNode('optimize')->defaultFalse()->end()
                                    ->booleanNode('flatten')->defaultTrue()->end()
                                    ->scalarNode('format')->defaultNull()->end()
                                    ->booleanNode('animated')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->scalarNode('default_image')->defaultNull()->end()
                            ->scalarNode('secret')->defaultNull()->end()
                            ->booleanNode('async')->defaultNull()->end()
                            ->booleanNode('exposed')->defaultFalse()->end()
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
            ->end()
        ;

        return $builder;
    }

    private function addResolversSections(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder('resolvers');
        $node = $builder->getRootNode();
        $normalization = static function ($conf): array {
            return ['type' => 'custom', 'service' => $conf];
        };

        $children = $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->beforeNormalization()
                    ->ifTrue(static fn ($v): bool => \is_string($v))
                    ->then($normalization)
                ->end()
                ->children()
                    ->enumNode('type')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->values($this->getResolverNames())
                    ->end()
                    ->scalarNode('service')->defaultNull()->end()
                ->end()
        ;

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
                ->end()
        ;

        foreach ($this->loadersFactories as $factory) {
            $factory->addConfiguration($children);
        }

        return $node;
    }

    /**
     * @return list<string>
     */
    private function getResolverNames(): array
    {
        $names = \array_values(\array_map(function (ResolverFactoryInterface $factory): string {
            return $factory->getName();
        }, $this->resolversFactories));

        $names[] = 'custom';

        return $names;
    }
}
