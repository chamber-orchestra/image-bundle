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
 * The attribute is repeatable: multiple instances on the same property
 * produce a keyed map in the serialized output.
 *
 * Usage:
 *   #[ImageFilter(filter: 'fill', width: 300, height: 300)]
 *   #[ImageFilter(key: 'hero', filter: 'fit', width: 800, height: 400)]
 *   public File|null $avatar = null;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class ImageFilter
{
    /**
     * @param string    $key       Unique key in the serialized output (default: "default")
     * @param string    $filter    Processor type: fill | fit | optimize
     * @param int       $width     Logical width in CSS pixels
     * @param int       $height    Logical height in CSS pixels (0 = auto)
     * @param list<int> $densities Pixel densities to generate (e.g. [1, 2, 3])
     * @param string    $preset    Named filter from chamber_orchestra_image.filters.* config
     */
    public function __construct(
        public readonly string $key = 'default',
        public readonly string $filter = 'fill',
        public readonly int $width = 0,
        public readonly int $height = 0,
        public readonly array $densities = [1, 2, 3, 4],
        public readonly string $preset = 'default',
    ) {
    }
}
