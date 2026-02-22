<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Twig;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use Twig\Extension\RuntimeExtensionInterface;

readonly class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(private CacheManager $cacheManager)
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fit(string $path, int $width = 0, int $height = 0, array $config = []): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fit().');
        }

        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'fit' => [
                    'width' => $width,
                    'height' => $height,
                    'density' => 1,
                ],
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fill(string $path, int $width = 0, int $height = 0, array $config = []): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fill().');
        }

        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'fill' => [
                    'width' => $width,
                    'height' => $height,
                    'density' => 1,
                ],
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function optimize(string $path, int $width = 1200, array $config = []): string
    {
        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'optimize' => [
                    'width' => $width,
                    'height' => 0,
                    'density' => 2,
                ],
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function applyFilter(string $path, array $config = [], string $filter = 'default', ?string $resolver = null): string
    {
        return $this->cacheManager->getBrowserPath($path, $filter, $config, $resolver);
    }
}
