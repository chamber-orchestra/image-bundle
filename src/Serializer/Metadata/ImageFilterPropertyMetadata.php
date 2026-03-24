<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Serializer\Metadata;

/**
 * Scalar-only snapshot of one #[ImageFilter] attribute instance.
 * Safe for serialization into any Symfony cache adapter.
 */
final readonly class ImageFilterPropertyMetadata
{
    /**
     * @param list<int> $densities
     */
    public function __construct(
        public string $key,
        public string $filter,
        public int $width,
        public int $height,
        public array $densities,
        public string $preset,
    ) {
    }
}
