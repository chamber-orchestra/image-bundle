<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\ImageInterface;

class InterlaceProcessor extends AbstractProcessor
{
    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        $mode = ImageInterface::INTERLACE_LINE;
        if (!empty($options['mode'])) {
            $mode = $options['mode'];
        }

        $image->interlace($mode);

        return $image;
    }
}
