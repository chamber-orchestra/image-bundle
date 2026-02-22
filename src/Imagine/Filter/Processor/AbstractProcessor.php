<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractProcessor implements ProcessorInterface
{
    public static function getIndexName(): string
    {
        $shortName = \substr(static::class, \strrpos(static::class, '\\') + 1);
        $pos = \strpos($shortName, 'Processor');

        if (false === $pos) {
            throw new \LogicException(\sprintf('Processor class "%s" must contain "Processor" in its name. Override getIndexName() to set it explicitly.', static::class));
        }

        return Container::underscore(\substr($shortName, 0, $pos));
    }
}
