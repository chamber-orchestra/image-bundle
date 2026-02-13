<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('image_filter', [ImageRuntime::class, 'imageFilter']),
            new TwigFilter('fit', [ImageRuntime::class, 'fit']),
            new TwigFilter('fill', [ImageRuntime::class, 'fill']),
            new TwigFilter('optimize', [ImageRuntime::class, 'optimize']),
        ];
    }
}
