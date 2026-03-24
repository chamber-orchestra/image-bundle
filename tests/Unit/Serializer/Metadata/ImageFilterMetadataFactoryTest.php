<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Serializer\Metadata;

use ChamberOrchestra\FileBundle\Model\File;
use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use ChamberOrchestra\ImageBundle\Serializer\Metadata\ImageFilterMetadataFactory;
use ChamberOrchestra\ImageBundle\Serializer\Metadata\ImageFilterPropertyMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class ImageFilterMetadataFactoryTest extends TestCase
{
    #[Test]
    public function returnsMetadataForAnnotatedClass(): void
    {
        $factory = new ImageFilterMetadataFactory();
        $metadata = $factory->getMetadata(SonataCoverView::class);

        self::assertArrayHasKey('coverArt', $metadata);
        self::assertCount(1, $metadata['coverArt']);

        $filter = $metadata['coverArt'][0];
        self::assertInstanceOf(ImageFilterPropertyMetadata::class, $filter);
        self::assertSame('default', $filter->key);
        self::assertSame('fill', $filter->filter);
        self::assertSame(300, $filter->width);
        self::assertSame(300, $filter->height);
        self::assertSame([1, 2, 3, 4], $filter->densities);
        self::assertSame('default', $filter->preset);
    }

    #[Test]
    public function returnsMultipleFiltersForRepeatableAttribute(): void
    {
        $factory = new ImageFilterMetadataFactory();
        $metadata = $factory->getMetadata(RecitalPosterView::class);

        self::assertArrayHasKey('poster', $metadata);
        self::assertCount(2, $metadata['poster']);
        self::assertSame('thumbnail', $metadata['poster'][0]->key);
        self::assertSame('hero', $metadata['poster'][1]->key);
    }

    #[Test]
    public function returnsEmptyArrayForClassWithoutAttributes(): void
    {
        $factory = new ImageFilterMetadataFactory();

        self::assertSame([], $factory->getMetadata(PlainView::class));
    }

    #[Test]
    public function hasMetadataReturnsTrueForAnnotatedClass(): void
    {
        $factory = new ImageFilterMetadataFactory();

        self::assertTrue($factory->hasMetadata(SonataCoverView::class));
    }

    #[Test]
    public function hasMetadataReturnsFalseForPlainClass(): void
    {
        $factory = new ImageFilterMetadataFactory();

        self::assertFalse($factory->hasMetadata(PlainView::class));
    }

    #[Test]
    public function rejectsOutOfRangeDensity(): void
    {
        $factory = new ImageFilterMetadataFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Density 5.*out of range/');

        $factory->getMetadata(InvalidDensityView::class);
    }

    #[Test]
    public function rejectsZeroDensity(): void
    {
        $factory = new ImageFilterMetadataFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Density 0.*out of range/');

        $factory->getMetadata(ZeroDensityView::class);
    }

    #[Test]
    public function acceptsObjectInstance(): void
    {
        $factory = new ImageFilterMetadataFactory();
        $object = new SonataCoverView();

        self::assertArrayHasKey('coverArt', $factory->getMetadata($object));
    }

    #[Test]
    public function inMemoryCacheAvoidsDuplicateReflection(): void
    {
        $factory = new ImageFilterMetadataFactory();

        $first = $factory->getMetadata(SonataCoverView::class);
        $second = $factory->getMetadata(SonataCoverView::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function writesCacheOnMissAndReadsOnHit(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->exactly(2))->method('isHit')->willReturn(false, true);
        $item->expects($this->once())->method('set');
        $item->expects($this->once())->method('expiresAfter')->with(86400);
        $item->method('get')->willReturn(['coverArt' => [
            new ImageFilterPropertyMetadata('default', 'fill', 300, 300, [1, 2, 3, 4], 'default'),
        ]]);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item);

        // First call: miss → reflect + save
        $factory = new ImageFilterMetadataFactory($pool, false);
        $factory->getMetadata(SonataCoverView::class);

        // Clear in-memory cache to test PSR-6 read path
        $factory2 = new ImageFilterMetadataFactory($pool, false);
        $factory2->getMetadata(SonataCoverView::class);
    }

    #[Test]
    public function debugModeUsesShortTtlAndFingerprint(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(false);
        $item->expects($this->once())->method('set');
        $item->expects($this->once())->method('expiresAfter')->with(60);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->with($this->callback(
            static fn (string $key): bool => \str_contains($key, 'SonataCoverView') && 1 === \preg_match('/_[a-f0-9]{8}$/', $key),
        ))->willReturn($item);
        $pool->expects($this->once())->method('save');

        $factory = new ImageFilterMetadataFactory($pool, true);
        $factory->getMetadata(SonataCoverView::class);
    }
}

/**
 * @internal
 */
final class SonataCoverView
{
    public string $title = 'Moonlight Sonata';

    #[ImageFilter(filter: 'fill', width: 300, height: 300)]
    public ?File $coverArt = null;
}

/**
 * @internal
 */
final class RecitalPosterView
{
    #[ImageFilter(key: 'thumbnail', filter: 'fill', width: 100, height: 100)]
    #[ImageFilter(key: 'hero', filter: 'fit', width: 800, height: 400)]
    public ?File $poster = null;
}

/**
 * @internal
 */
final class PlainView
{
    public string $title = 'Symphony No. 5';
}

/**
 * @internal
 */
final class InvalidDensityView
{
    #[ImageFilter(filter: 'fill', width: 100, height: 100, densities: [1, 5])]
    public ?File $coverArt = null;
}

/**
 * @internal
 */
final class ZeroDensityView
{
    #[ImageFilter(filter: 'fill', width: 100, height: 100, densities: [0])]
    public ?File $coverArt = null;
}
