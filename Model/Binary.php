<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Model;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

readonly class Binary implements BinaryInterface
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string|null $format = null
    )
    {
    }

    public function getContent(): string
    {
        return $this->content;
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
