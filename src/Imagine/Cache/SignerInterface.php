<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

interface SignerInterface
{
    /**
     * Return the hash for path and runtime config.
     *
     * @param array<string, mixed> $config
     */
    public function sign(string $path, string $secret, array $config): string;

    /**
     * Returns base prefix for path.
     */
    public function getSignedPrefix(string $path, string $secret): string;

    /**
     * Check hash is correct.
     *
     * @param array<string, mixed> $config
     */
    public function check(string $hash, string $path, string $secret, array $config): bool;
}
