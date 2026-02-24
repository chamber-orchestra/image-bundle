<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class OptimizeProcessor extends FitProcessor
{
    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
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
            return new Box($imgSize->getWidth(), $imgSize->getHeight());
        }

        return $size;
    }
}
