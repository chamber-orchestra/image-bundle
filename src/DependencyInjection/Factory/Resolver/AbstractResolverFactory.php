<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

abstract class AbstractResolverFactory implements ResolverFactoryInterface
{
    protected static string $namePrefix = 'chamber_orchestra_image.cache.resolver';

    final protected function setTaggedDefinition(string $name, Definition $definition, ContainerBuilder $container): string
    {
        $definition
            ->setAutowired(true)
            ->addTag(static::$namePrefix);

        $container->setDefinition(
            $id = \sprintf('%s.%s', static::$namePrefix, $name),
            $definition
        );

        return $id;
    }
}
