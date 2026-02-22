<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * @param array<string, mixed> $options
     */
    public function process(BinaryInterface $binary, array $options): BinaryInterface;

    public static function getIndexName(): string;
}
