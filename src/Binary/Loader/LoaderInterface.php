<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

interface LoaderInterface
{
    /**
     * Retrieve the Image represented by the given path.
     *
     * The path may be a file path on a filesystem, or any unique identifier among the storage engine implemented by this Loader.
     *
     * @return BinaryInterface|string An image binary content
     */
    public function find(string $path): BinaryInterface|string;

    public function getName(): string;
}
