<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ImageExtension extends AbstractExtension
{
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
