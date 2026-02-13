<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use Symfony\Component\DependencyInjection\Container;

abstract class AbstractLoader implements LoaderInterface
{
    public function getName(): string
    {
        return Container::underscore(\str_replace('Loader', '', __CLASS__));
    }
}
