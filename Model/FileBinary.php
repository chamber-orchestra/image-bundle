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
        return \file_get_contents($this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
