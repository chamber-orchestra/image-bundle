<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Model;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

readonly class Binary implements BinaryInterface
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public ?string $format = null
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
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
