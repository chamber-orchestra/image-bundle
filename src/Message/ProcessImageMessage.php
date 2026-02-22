<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Message;

readonly class ProcessImageMessage
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $path,
        public string $filter,
        public array $config = [],
        public ?string $resolver = null,
    ) {
    }
}
