<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Strip;
use Imagine\Image\ImageInterface;

class StripProcessor extends AbstractProcessor
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $config
     */
    #[\Override]
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        return (new Strip())->apply($image);
    }
}
