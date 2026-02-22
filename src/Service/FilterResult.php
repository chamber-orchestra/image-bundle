<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Service;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

readonly class FilterResult
{
    private function __construct(
        public ?string $url,
        public ?BinaryInterface $binary,
        public bool $async,
    ) {
    }

    public static function resolved(string $url): self
    {
        return new self($url, null, false);
    }

    public static function deferred(BinaryInterface $sourceBinary): self
    {
        return new self(null, $sourceBinary, true);
    }
}
