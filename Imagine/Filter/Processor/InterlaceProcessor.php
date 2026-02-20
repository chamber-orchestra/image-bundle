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
        $validModes = [
            ImageInterface::INTERLACE_NONE,
            ImageInterface::INTERLACE_LINE,
            ImageInterface::INTERLACE_PLANE,
            ImageInterface::INTERLACE_PARTITION,
        ];

        $mode = $options['mode'] ?? ImageInterface::INTERLACE_LINE;

        if (!\in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid interlace mode "%s". Valid modes: %s.',
                $mode,
                \implode(', ', $validModes)
            ));
        }

        $image->interlace($mode);

        return $image;
    }
}
