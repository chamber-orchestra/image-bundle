<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\NotResolvableException;

interface ResolverInterface
{
    /**
     * Checks whether the given path is stored within this Resolver.
     *
     * @param string $path
     * @param string $filter
     *
     * @return bool
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
     * @param string $path       The source image path
     * @param string $filter     The filter name whose cached artifact to remove
     * @param string $runtimeDir The runtime prefix for signed runtime-processed variants
     */
    public function remove(string $path, string $filter, string $runtimeDir): void;
}
