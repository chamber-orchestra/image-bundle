<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

interface LoaderInterface
{
    /**
     * Retrieve the Image represented by the given path.
     *
     * The path may be a file path on a filesystem, or any unique identifier among the storage engine implemented by this Loader.
     *
     * @param string $path
     *
     * @return BinaryInterface|string An image binary content
     */
    public function find(string $path): BinaryInterface|string;

    /**
     * @return string
     */
    public function getName(): string;
}
