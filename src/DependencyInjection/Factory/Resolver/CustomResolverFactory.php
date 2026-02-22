<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomResolverFactory extends AbstractResolverFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        /** @var string $service */
        $service = $config['service'];
        $definition = new ChildDefinition($service);

        return $this->setTaggedDefinition($name, $definition, $container);
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('service')
                    ->defaultNull()
                ->end()
            ->end()
        ;
    }
}
