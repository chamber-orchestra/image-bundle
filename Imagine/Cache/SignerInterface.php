<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

interface SignerInterface
{
    /**
     * Return the hash for path and runtime config.
     */
    public function sign(string $path, ?array $runtimeConfig = null): string;

    /**
     * Returns base prefix for path.
     */
    public function getSignedPrefix(string $path): string;

    /**
     * Check hash is correct.
     */
    public function check(string $hash, string $path, ?array $runtimeConfig = null): bool;
}
