<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Locator;

class FileSystemInsecureLocator extends FileSystemLocator
{
    public function getName(): string
    {
        return 'filesystem_insecure';
    }

    protected function generateAbsolutePath(string $root, string $path): string|null
    {
        if (\str_contains($path, '..'.DIRECTORY_SEPARATOR)
            || \str_contains($path, DIRECTORY_SEPARATOR.'..')
            || '..' === $path
            || false === ($absolute = \realpath($root.DIRECTORY_SEPARATOR.$path))
        ) {
            return null;
        }

        // Verify resolved path is still under root
        if (!\str_starts_with($absolute, $root)) {
            return null;
        }

        return $absolute;
    }
}
