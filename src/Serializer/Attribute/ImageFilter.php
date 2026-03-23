<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Serializer\Attribute;

/**
 * Marks a File property for automatic image URL generation during serialization.
 *
 * The normalizer will produce avif/webp/src × 1x/2x/3x signed URLs,
 * mirroring the structure of the Twig image macros.
 *
 * Usage:
 *   #[ImageFilter(filter: 'fill', width: 300, height: 300)]
 *   public File|null $avatar = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ImageFilter
{
    /**
     * @param string $filter Processor type: fill | fit | optimize
     * @param int    $width  Logical width in CSS pixels
     * @param int    $height Logical height in CSS pixels (0 = auto)
     * @param string $preset Image filter preset name from chamber_orchestra_image config
     */
    public function __construct(
        public readonly string $filter = 'fill',
        public readonly int $width = 0,
        public readonly int $height = 0,
        public readonly string $preset = 'default',
    ) {
    }
}
