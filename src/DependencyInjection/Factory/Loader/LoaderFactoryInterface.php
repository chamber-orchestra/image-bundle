<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

interface LoaderFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string;

    public function getName(): string;

    public function addConfiguration(ArrayNodeDefinition $builder): void;
}
