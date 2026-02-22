<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\NotResolvableException;

interface ResolverInterface
{
    /**
     * Checks whether the given path is stored within this Resolver.
     */
    public function isStored(string $path, string $filter): bool;

    /**
     * Resolves filtered path for rendering in the browser.
     *
     * @param string $path   The path where the original file is expected to be
     * @param string $filter The name of the imagine filter in effect
     *
     * @return string The absolute URL of the cached image
     *
     * @throws NotResolvableException
     */
    public function resolve(string $path, string $filter): string;

    /**
     * Stores the content of the given binary.
     *
     * @param BinaryInterface $binary The image binary to store
     * @param string          $path   The path where the original file is expected to be
     * @param string          $filter The name of the imagine filter in effect
     */
    public function store(BinaryInterface $binary, string $path, string $filter): void;

    /**
     * Removes all cached variants stored under the given prefix directory.
     */
    public function remove(string $prefix): void;
}
