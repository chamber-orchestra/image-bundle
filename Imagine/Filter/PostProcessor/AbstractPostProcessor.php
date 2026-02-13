<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractPostProcessor implements PostProcessorInterface
{
    public static function getIndexName(): string
    {
        $name = \substr(static::class, \strrpos(static::class, '\\') + 1);
        $name = \substr($name, 0, \strpos($name, 'PostProcessor'));

        return Container::underscore($name);
    }
}
