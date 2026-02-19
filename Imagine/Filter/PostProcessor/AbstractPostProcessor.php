<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractPostProcessor implements PostProcessorInterface
{
    public static function getIndexName(): string
    {
        $shortName = \substr(static::class, \strrpos(static::class, '\\') + 1);
        $pos = \strpos($shortName, 'PostProcessor');

        if (false === $pos) {
            throw new \LogicException(\sprintf(
                'PostProcessor class "%s" must contain "PostProcessor" in its name. Override getIndexName() to set it explicitly.',
                static::class
            ));
        }

        return Container::underscore(\substr($shortName, 0, $pos));
    }
}
