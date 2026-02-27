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
    public function fillPropagatesCustomDensityIntoProcessor(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'default',
                $this->callback(fn ($c) => 2 === $c['processors']['fill']['density']),
                null
            )
            ->willReturn('/url');

        $this->runtime->fill('photo.jpg', 400, 300, ['fill' => ['density' => 2]]);
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
    public function imageFilterPassesFilterNameToCacheManager(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'scores/moonlight.jpg',
                'sonata',
                ['processors' => ['fit' => ['width' => 400]]],
                null,
            )
            ->willReturn('/media/sonata/moonlight.jpg');

        $result = $this->runtime->imageFilter('scores/moonlight.jpg', 'sonata', [
            'processors' => ['fit' => ['width' => 400]],
        ]);

        self::assertSame('/media/sonata/moonlight.jpg', $result);
    }

    #[Test]
    public function fitPassesCustomFilterName(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'nocturne',
                $this->anything(),
                null,
            )
            ->willReturn('/url');

        $this->runtime->fit('photo.jpg', 800, 0, [], 'nocturne');
    }

    #[Test]
    public function fillPassesCustomFilterName(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'prelude',
                $this->anything(),
                null,
            )
            ->willReturn('/url');

        $this->runtime->fill('photo.jpg', 400, 300, [], 'prelude');
    }

    #[Test]
    public function optimizePassesCustomFilterName(): void
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('getBrowserPath')
            ->with(
                'photo.jpg',
                'aria',
                $this->anything(),
                null,
            )
            ->willReturn('/url');

        $this->runtime->optimize('photo.jpg', 1200, [], 'aria');
    }
}
