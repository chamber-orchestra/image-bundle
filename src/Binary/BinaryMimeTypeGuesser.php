<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary;

use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

readonly class BinaryMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function __construct(private FileinfoMimeTypeGuesser $guesser)
    {
    }

    public function isGuesserSupported(): bool
    {
        return $this->guesser->isGuesserSupported();
    }

    public function guessMimeType(string $binary): ?string
    {
        if (false === $tmpFile = \tempnam(\sys_get_temp_dir(), 'dev-image-bundle')) {
            throw new RuntimeException(\sprintf('Temp file can not be created in "%s".', \sys_get_temp_dir()));
        }

        try {
            if (false === \file_put_contents($tmpFile, $binary)) {
                throw new RuntimeException(\sprintf('Could not write to temp file "%s".', $tmpFile));
            }

            return $this->guesser->guessMimeType($tmpFile);
        } finally {
            \unlink($tmpFile);
        }
    }
}
