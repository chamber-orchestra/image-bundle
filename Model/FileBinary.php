<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Model;

use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;

readonly class FileBinary implements FileBinaryInterface
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public string|null $format = null)
    {
    }

    public function getContent(): string
    {
        $content = \file_get_contents($this->path);

        if (false === $content) {
            throw new \RuntimeException(\sprintf(
                'Could not read file at "%s": %s',
                $this->path,
                \error_get_last()['message'] ?? 'unknown error'
            ));
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

    public function getFormat(): string|null
    {
        return $this->format;
    }
}
