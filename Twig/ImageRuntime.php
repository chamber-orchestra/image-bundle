<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Twig;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use Twig\Extension\RuntimeExtensionInterface;

readonly class ImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(private CacheManager $cacheManager)
    {
    }

    public function imageFilter(string $path, string $filter, array $runtimeConfig = [], string|null $resolver = null): string
    {
        return $this->applyFilter($path, $runtimeConfig, $filter, $resolver);
    }

    public function fit(string $path, int $width = 0, int $height = 0, array $config = []): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fit().');
        }

        $config = \array_replace_recursive([
            'fit' => [
                'width' => $width,
                'height' => $height,
                'density' => 1,
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    public function fill(string $path, int $width = 0, int $height = 0, array $config = []): string
    {
        if (0 === $width && 0 === $height) {
            throw new \InvalidArgumentException('At least one of width or height must be non-zero for fill().');
        }

        $config = \array_replace_recursive([
            'fill' => [
                'width' => $width,
                'height' => $height,
                'density' => 1,
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    public function optimize(string $path, int|null $width = 1200, array $config = []): string
    {
        $config = \array_replace_recursive([
            'optimize' => [
                'width' => (int) $width,
                'height' => 0,
                'density' => 2,
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    private function applyFilter(string $path, array $config = [], string $filter = 'default', string|null $resolver = null): string
    {
        return $this->cacheManager->getBrowserPath($path, $filter, $config, $resolver);
    }
}