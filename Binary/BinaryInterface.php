<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary;

interface BinaryInterface
{
    public function getContent(): string;

    public function getMimeType(): string;

    public function getFormat(): string;
}
