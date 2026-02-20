<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Locator;

interface LocatorInterface
{
    public function setOptions(array $options = []): void;

    public function locate(string $path): string;

    public function getName(): string;
}
