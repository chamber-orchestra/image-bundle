<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\FileSystemLoader;
use ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemInsecureLocator;
use ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FileSystemLoaderFactory extends AbstractLoaderFactory implements LoaderFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(FileSystemLoader::class);
        /** @var string $locator */
        $locator = $config['locator'];
        $definition->setArgument('$locator', new Reference($locator));
        /** @var list<string> $dataRoot */
        $dataRoot = $config['data_root'];
        $definition->setArgument('$dataRoots', $dataRoot);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    public function getName(): string
    {
        return 'filesystem';
    }

    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('type')->defaultValue($this->getName())->cannotBeEmpty()->end()
                ->enumNode('locator')
                    ->values([FileSystemInsecureLocator::class, FileSystemLocator::class])
                    ->info('Using the "filesystem_insecure" locator is not recommended due to a less secure resolver mechanism, but is provided for those using heavily symlinked projects.')
                    ->defaultValue(FileSystemLocator::class)
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
            ->end()
        ;
    }
}
