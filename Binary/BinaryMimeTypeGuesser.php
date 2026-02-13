<?php

declare(strict_types=1);

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

    public function guessMimeType(string $binary): string|null
    {
        if (!$this->isBinary($binary)) {
            return null;
        }

        if (false === $tmpFile = \tempnam(\sys_get_temp_dir(), 'dev-image-bundle')) {
            throw new RuntimeException(sprintf('Temp file can not be created in "%s".', \sys_get_temp_dir()));
        }

        try {
            \file_put_contents($tmpFile, $binary);
            return $this->guesser->guessMimeType($tmpFile);
        } finally {
            \unlink($tmpFile);
        }
    }

    private function isBinary(string $binary): bool
    {
        return !\ctype_print($binary);
    }
}
