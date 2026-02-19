<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class OptimizeProcessor extends FitProcessor
{
    protected function getOutputSize(ImageInterface $image, array $options = []): Box
    {
        /*
         * this filter used when width or height is equal to zero
         * therefore parent call returns size with image aspect ratio
         */
        $size = parent::getOutputSize($image, $options);

        if ($size->contains($imgSize = $image->getSize())) {
            // if required size bigger than image size
            // change size to image size
            $size = $imgSize;
        }

        return $size;
    }
}
