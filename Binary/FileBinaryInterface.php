<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary;

interface FileBinaryInterface extends BinaryInterface
{
    public function getPath(): string;
}
