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
    public function imageFilter(string $path, string $filter, array $config = []): string
    {
        return $this->applyFilter($path, $config, $filter);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fit(string $path, int $width = 0, int $height = 0, array $config = [], string $filter = 'default'): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fit().');
        }

        /** @var array<string, mixed> $fitOptions */
        $fitOptions = \is_array($config['fit'] ?? null) ? $config['fit'] : [];

        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'fit' => \array_replace([
                    'width' => $width,
                    'height' => $height,
                    'density' => 1,
                ], $fitOptions),
            ],
        ], $config);

        return $this->applyFilter($path, $config, $filter);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fill(string $path, int $width = 0, int $height = 0, array $config = [], string $filter = 'default'): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fill().');
        }

        /** @var array<string, mixed> $fillOptions */
        $fillOptions = \is_array($config['fill'] ?? null) ? $config['fill'] : [];

        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'fill' => \array_replace([
                    'width' => $width,
                    'height' => $height,
                    'density' => 1,
                ], $fillOptions),
            ],
        ], $config);

        return $this->applyFilter($path, $config, $filter);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function optimize(string $path, int $width = 1200, array $config = [], string $filter = 'default'): string
    {
        /** @var array<string, mixed> $optimizeOptions */
        $optimizeOptions = \is_array($config['optimize'] ?? null) ? $config['optimize'] : [];

        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'processors' => [
                'optimize' => \array_replace([
                    'width' => $width,
                    'height' => 0,
                    'density' => 2,
                ], $optimizeOptions),
            ],
        ], $config);

        return $this->applyFilter($path, $config, $filter);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function applyFilter(string $path, array $config, string $filter = 'default', ?string $resolver = null): string
    {
        return $this->cacheManager->getBrowserPath($path, $filter, $config, $resolver);
    }
}
