<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

/**
 * Interface for PostProcessors - handlers which can operate on binaries prepared in FilterManager.
 *
 * @see ConfigurablePostProcessorInterface For a means to configure these at run-time
 */
interface PostProcessorInterface
{
    /**
     * @param BinaryInterface $binary
     *
     * @param array           $options
     * @return BinaryInterface
     */
    public function process(BinaryInterface $binary, array $options): BinaryInterface;

    /**
     * @return string
     */
    public static function getIndexName(): string;
}
