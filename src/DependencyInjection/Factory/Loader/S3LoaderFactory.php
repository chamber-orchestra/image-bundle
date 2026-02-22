<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\S3Loader;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class S3LoaderFactory extends AbstractLoaderFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition(S3Loader::class);

        /** @var string $storageName */
        $storageName = $config['storage'];
        $definition->setArgument('$storage', new Reference(\sprintf('chamber_orchestra_file.storage.%s', $storageName)));

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    public function getName(): string
    {
        return 's3';
    }

    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('storage')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                    ->info('Name of the file-bundle storage to use for loading source images.')
                ->end()
            ->end()
        ;
    }
}
