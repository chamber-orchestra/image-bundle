<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Crop;
use Imagine\Filter\Basic\Resize;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class FillProcessor extends AbstractResizeProcessor
{
    protected function doApply(ImageInterface $image, Box $size, float $density, array $options = []): ImageInterface
    {
        if ($size->contains($imgSize = $image->getSize())) {
            /*
             * Passed image is smaller than target - do nothing,
             * maintain density
             */
            return self::DEFAULT_DENSITY !== $density
                ? (new Resize($imgSize->scale($density), $options['filter']))->apply($image)
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
        $image = $this->resize($image, $scaled, $options['filter']);

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
            \max(0, \round(($imgSize->getWidth() - $size->getWidth()) / 2)),
            \max(0, \round(($imgSize->getHeight() - $size->getHeight()) / 2))
        );

        return (new Crop($point, $size))->apply($image);
    }
}
