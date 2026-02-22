<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary;

interface BinaryInterface
{
    public function getContent(): string;

    public function getMimeType(): string;

    public function getFormat(): ?string;
}
