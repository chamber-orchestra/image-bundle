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
        if (\str_contains($path, '..'.DIRECTORY_SEPARATOR) ||
            \str_contains($path, DIRECTORY_SEPARATOR.'..') ||
            false === \file_exists($absolute = $root.DIRECTORY_SEPARATOR.$path)
        ) {
            return null;
        }

        return $absolute;
    }
}
