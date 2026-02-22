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

interface ProcessorInterface
{
    /**
     * Loads and applies a filter on the given image.
     *
     * @param array<string, mixed> $options
     * @param array<string, mixed> $config
     */
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface;

    public static function getIndexName(): string;
}
