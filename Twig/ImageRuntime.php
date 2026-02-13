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

    public function fit(string $path, int|null $width = null, int|null $height = null, array $config = []): string
    {
        $config = \array_replace_recursive([
            'fit' => [
                'width' => (int) $width,
                'height' => (int) $height,
                'density' => 1,
            ],
        ], $config);

        return $this->applyFilter($path, $config);
    }

    public function fill(string $path, int|null $width = 0, int|null $height = 0, array $config = []): string
    {
        $config = \array_replace_recursive([
            'fill' => [
                'width' => (int) $width,
                'height' => (int) $height,
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