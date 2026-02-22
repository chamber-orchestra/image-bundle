<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Locator;

interface LocatorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options = []): void;

    public function locate(string $path): string;

    public function getName(): string;
}
