<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Filter\Basic\Strip;
use Imagine\Image\ImageInterface;

class StripProcessor extends AbstractProcessor
{
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        return (new Strip())->apply($image);
    }
}
