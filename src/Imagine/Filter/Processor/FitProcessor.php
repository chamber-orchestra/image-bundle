<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Resize;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class FitProcessor extends AbstractResizeProcessor
{
    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    protected function doApply(ImageInterface $image, Box $size, float $density, array $options = []): ImageInterface
    {
        /** @var string $filter */
        $filter = $options['filter'];

        if ($size->contains($imgSize = $image->getSize())) {
            /*
             * Passed image is smaller than target - do nothing,
             * maintain the density
             */
            return self::DEFAULT_DENSITY !== $density
                ? (new Resize($imgSize->scale($density), $filter))->apply($image)
                : $image;
        }

        return $this->resize($image, $size->scale($density), $filter);
    }

    private function resize(ImageInterface $image, Box $size, string $filter): ImageInterface
    {
        $imgSize = $image->getSize();
        $imgRatio = $imgSize->getWidth() / $imgSize->getHeight();
        $ratio = $size->getWidth() / $size->getHeight();
        $size = ($ratio < $imgRatio) ? $imgSize->widen($size->getWidth()) : $imgSize->heighten($size->getHeight());

        return (new Resize($size, $filter))->apply($image);
    }
}
