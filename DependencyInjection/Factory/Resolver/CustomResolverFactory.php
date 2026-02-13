<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomResolverFactory extends AbstractResolverFactory
{
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        $definition = new ChildDefinition($config['service']);

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
            ->scalarNode('service')->defaultNull()->end()
            ->end();
    }
}
