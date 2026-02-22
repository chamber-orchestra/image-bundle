<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WebPathResolverFactory extends AbstractResolverFactory implements ResolverFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(WebPathResolver::class);

        $definition
            ->setArgument('$webRootDir', $config['web_root'])
            ->setArgument('$cachePrefix', $config['cache_prefix']);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    public function getName(): string
    {
        return 'web_path';
    }

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
            ->end()
        ;
    }
}
