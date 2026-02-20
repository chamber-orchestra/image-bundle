<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\ImageInterface;

interface ProcessorInterface
{
    /**
     * Loads and applies a filter on the given image.
     *
     * @param ImageInterface $image
     * @param array          $options
     *
     * @param array          $config
     * @return ImageInterface
     */
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface;

    /**
     * @return string
     */
    public static function getIndexName(): string;
}
