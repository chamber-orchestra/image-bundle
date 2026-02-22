<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Model;

use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;

readonly class FileBinary implements FileBinaryInterface
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public ?string $format = null)
    {
    }

    public function getContent(): string
    {
        $content = \file_get_contents($this->path);

        if (false === $content) {
            throw new \RuntimeException(\sprintf('Could not read file at "%s": %s', $this->path, \error_get_last()['message'] ?? 'unknown error'));
        }

        return $content;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }
}
