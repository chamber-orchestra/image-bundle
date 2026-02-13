<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface LoaderFactoryInterface
{
    public function create(ContainerBuilder $container, string $name, array $config): string;

    public function getName(): string;

    public function addConfiguration(ArrayNodeDefinition $builder): void;
}
