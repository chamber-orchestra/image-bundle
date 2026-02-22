<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Locator;

class FileSystemInsecureLocator extends FileSystemLocator
{
    public function getName(): string
    {
        return 'filesystem_insecure';
    }

    protected function generateAbsolutePath(string $root, string $path): ?string
    {
        if (\str_contains($path, '..'.DIRECTORY_SEPARATOR)
            || \str_contains($path, DIRECTORY_SEPARATOR.'..')
            || '..' === $path
            || false === ($absolute = \realpath($root.DIRECTORY_SEPARATOR.$path))
        ) {
            return null;
        }

        // Verify resolved path is still under root
        if (!\str_starts_with($absolute, $root.\DIRECTORY_SEPARATOR) && $absolute !== $root) {
            return null;
        }

        return $absolute;
    }
}
