<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\ImageInterface;

class InterlaceProcessor extends AbstractProcessor
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $config
     */
    #[\Override]
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        $validModes = [
            ImageInterface::INTERLACE_NONE,
            ImageInterface::INTERLACE_LINE,
            ImageInterface::INTERLACE_PLANE,
            ImageInterface::INTERLACE_PARTITION,
        ];

        /** @var string $mode */
        $mode = $options['mode'] ?? ImageInterface::INTERLACE_LINE;

        if (!\in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid interlace mode "%s". Valid modes: %s.', $mode, \implode(', ', $validModes)));
        }

        $image->interlace($mode);

        return $image;
    }
}
