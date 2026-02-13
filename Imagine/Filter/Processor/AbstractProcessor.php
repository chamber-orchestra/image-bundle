<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractProcessor implements ProcessorInterface
{
    public static function getIndexName(): string
    {
        $name = \substr(static::class, \strrpos(static::class, '\\') + 1);
        $name = \substr($name, 0, \strpos($name, 'Processor'));

        return Container::underscore($name);
    }
}
