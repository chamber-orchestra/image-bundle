<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractLoader implements LoaderInterface
{
    public function getName(): string
    {
        $shortName = \substr(static::class, \strrpos(static::class, '\\') + 1);
        $suffix = 'Loader';
        $pos = \strrpos($shortName, $suffix);

        if (false === $pos || $pos !== \strlen($shortName) - \strlen($suffix)) {
            throw new \LogicException(\sprintf('Loader class "%s" must have a "%s" suffix. Override getName() to provide the service locator key explicitly.', static::class, $suffix));
        }

        return Container::underscore(\substr($shortName, 0, $pos));
    }
}
