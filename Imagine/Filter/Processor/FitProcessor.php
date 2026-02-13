<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Resize;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class FitProcessor extends AbstractResizeProcessor
{
    public function doApply(ImageInterface $image, Box $size, float $density, array $options = []): ImageInterface
    {
        if ($size->contains($imgSize = $image->getSize())) {
            /*
             * Passed image is smaller than target - do nothing,
             * maintain the density
             */
            return self::DEFAULT_DENSITY !== $density
                ? (new Resize($imgSize->scale($density), $options['filter']))->apply($image)
                : $image;
        }

        return $this->resize($image, $size->scale($density), $options['filter']);
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
