<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface ResolverFactoryInterface
{
    public function create(ContainerBuilder $container, string $name, array $config): string;

    public function getName(): string;

    public function addConfiguration(ArrayNodeDefinition $builder): void;
}
