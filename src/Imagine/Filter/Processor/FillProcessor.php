<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Crop;
use Imagine\Filter\Basic\Resize;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class FillProcessor extends AbstractResizeProcessor
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
             * maintain density
             */
            return self::DEFAULT_DENSITY !== $density
                ? (new Resize($imgSize->scale($density), $filter))->apply($image)
                : $image;
        }

        /**
         * Passed image is one of the following
         * - smaller than scaled target
         * - bigger than scaled
         * - bigger than original target,
         * Resize the image to match size and then crop.
         */
        $scaled = $size->scale($density);
        $image = $this->resize($image, $scaled, $filter);

        return $this->crop($image, $scaled);
    }

    private function resize(ImageInterface $image, Box $size, string $filter): ImageInterface
    {
        $imgSize = $image->getSize();
        $imgRatio = $imgSize->getWidth() / $imgSize->getHeight();
        $ratio = $size->getWidth() / $size->getHeight();
        $size = ($ratio < $imgRatio) ? $imgSize->heighten($size->getHeight()) : $imgSize->widen($size->getWidth());

        return (new Resize($size, $filter))->apply($image);
    }

    private function crop(ImageInterface $image, Box $size): ImageInterface
    {
        $imgSize = $image->getSize();
        // crop to needed part from resized image
        $point = new Point(
            (int) \max(0, \round(($imgSize->getWidth() - $size->getWidth()) / 2)),
            (int) \max(0, \round(($imgSize->getHeight() - $size->getHeight()) / 2))
        );

        return (new Crop($point, $size))->apply($image);
    }
}
