<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Twig;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Twig\ImageRuntime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ImageRuntimeTest extends TestCase
{
    private CacheManager $cacheManager;
    private ImageRuntime $runtime;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->runtime = new ImageRuntime($this->cacheManager);
    }

    #[Test]
    public function imageFilterDelegatesToCacheManager(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with('photo.jpg', 'thumbnail', [], null)
            ->willReturn('/media/cache/thumbnail/photo.jpg');

        $result = $this->runtime->imageFilter('photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/thumbnail/photo.jpg', $result);
    }

    #[Test]
    public function imageFilterPassesRuntimeConfigAndResolver(): void
    {
        $config = ['fit' => ['width' => 800]];

        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with('photo.jpg', 'thumbnail', $config, 'custom')
            ->willReturn('/url');

        $this->runtime->imageFilter('photo.jpg', 'thumbnail', $config, 'custom');
    }

    #[Test]
    public function fitThrowsWhenBothDimensionsAreZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/At least one of width or height/');

        $this->runtime->fit('photo.jpg', 0, 0);
    }

    #[Test]
    public function fitPassesWidthConfigToCacheManager(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                ['processors' => ['fit' => ['width' => 800, 'height' => 0, 'density' => 1]]],
                null
            )
            ->willReturn('/url');

        $this->runtime->fit('photo.jpg', 800, 0);
    }

    #[Test]
    public function fitPassesHeightConfigToCacheManager(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                ['processors' => ['fit' => ['width' => 0, 'height' => 600, 'density' => 1]]],
                null
            )
            ->willReturn('/url');

        $this->runtime->fit('photo.jpg', 0, 600);
    }

    #[Test]
    public function fitMergesExtraConfig(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($c) => isset($c['processors']['fit']) && isset($c['output'])),
                $this->anything()
            )
            ->willReturn('/url');

        $this->runtime->fit('photo.jpg', 800, 0, ['output' => ['format' => 'webp']]);
    }

    #[Test]
    public function fillThrowsWhenBothDimensionsAreZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/At least one of width or height/');

        $this->runtime->fill('photo.jpg', 0, 0);
    }

    #[Test]
    public function fillPassesDimensionsToCacheManager(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                ['processors' => ['fill' => ['width' => 400, 'height' => 300, 'density' => 1]]],
                null
            )
            ->willReturn('/url');

        $this->runtime->fill('photo.jpg', 400, 300);
    }

    #[Test]
    public function optimizeUsesDefaultWidth1200WithDensity2(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                ['processors' => ['optimize' => ['width' => 1200, 'height' => 0, 'density' => 2]]],
                null
            )
            ->willReturn('/url');

        $this->runtime->optimize('photo.jpg');
    }

    #[Test]
    public function optimizeUsesCustomWidth(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($c) => 800 === $c['processors']['optimize']['width']),
                $this->anything()
            )
            ->willReturn('/url');

        $this->runtime->optimize('photo.jpg', 800);
    }

    #[Test]
    public function avifUsesDefaultWidth1200WithDensity2AndAvifFormat(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                [
                    'processors' => ['optimize' => ['width' => 1200, 'height' => 0, 'density' => 2]],
                    'output' => ['format' => 'avif'],
                ],
                null
            )
            ->willReturn('/url');

        $this->runtime->avif('photo.jpg');
    }

    #[Test]
    public function avifUsesCustomWidth(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($c) => 800 === $c['processors']['optimize']['width'] && 'avif' === $c['output']['format']),
                $this->anything()
            )
            ->willReturn('/url');

        $this->runtime->avif('photo.jpg', 800);
    }

    #[Test]
    public function avifMergesExtraConfig(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($c) => 'avif' === $c['output']['format'] && 90 === $c['output']['quality']),
                $this->anything()
            )
            ->willReturn('/url');

        $this->runtime->avif('photo.jpg', 1200, ['output' => ['quality' => 90]]);
    }
}
