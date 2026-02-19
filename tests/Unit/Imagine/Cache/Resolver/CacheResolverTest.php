<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\CacheResolver;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\ResolverInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[AllowMockObjectsWithoutExpectations]
class CacheResolverTest extends TestCase
{
    private CacheItemPoolInterface $cache;
    private ResolverInterface $innerResolver;
    private CacheResolver $resolver;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->innerResolver = $this->createMock(ResolverInterface::class);
        $this->resolver = new CacheResolver($this->cache, $this->innerResolver);
    }

    #[Test]
    public function isStoredReturnsTrueWhenCacheHasItem(): void
    {
        $this->cache->method('hasItem')->willReturn(true);

        self::assertTrue($this->resolver->isStored('photo.jpg', 'thumb'));
    }

    #[Test]
    public function isStoredDelegatesToInnerResolverWhenCacheMisses(): void
    {
        $this->cache->method('hasItem')->willReturn(false);
        $this->innerResolver->method('isStored')->willReturn(true);

        self::assertTrue($this->resolver->isStored('photo.jpg', 'thumb'));
    }

    #[Test]
    public function isStoredReturnsFalseWhenBothMiss(): void
    {
        $this->cache->method('hasItem')->willReturn(false);
        $this->innerResolver->method('isStored')->willReturn(false);

        self::assertFalse($this->resolver->isStored('photo.jpg', 'thumb'));
    }

    #[Test]
    public function resolveReturnsCachedValueWhenPresent(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('get')->willReturn('/media/cache/thumb/photo.jpg');

        $this->cache->method('hasItem')->willReturn(true);
        $this->cache->method('getItem')->willReturn($item);

        $result = $this->resolver->resolve('photo.jpg', 'thumb');

        self::assertSame('/media/cache/thumb/photo.jpg', $result);
    }

    #[Test]
    public function resolveForwardsToInnerAndCachesWhenMissing(): void
    {
        $this->cache->method('hasItem')->willReturn(false);
        $this->innerResolver->method('resolve')->willReturn('/media/cache/thumb/photo.jpg');

        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(false);
        $indexItem->method('set')->willReturnSelf();
        $indexItem->method('expiresAfter')->willReturnSelf();

        $contentItem = $this->createMock(CacheItemInterface::class);
        $contentItem->method('set')->willReturnSelf();
        $contentItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('getItem')->willReturnOnConsecutiveCalls($indexItem, $contentItem);
        $this->cache->method('save')->willReturn(true);

        $result = $this->resolver->resolve('photo.jpg', 'thumb');

        self::assertSame('/media/cache/thumb/photo.jpg', $result);
    }

    #[Test]
    public function storeDelegatesToInnerResolver(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $this->innerResolver->expects($this->once())
            ->method('store')
            ->with($binary, 'photo.jpg', 'thumb');

        $this->resolver->store($binary, 'photo.jpg', 'thumb');
    }

    #[Test]
    public function removeDelegatesToInnerResolverAndClearsCache(): void
    {
        $this->innerResolver->expects($this->once())
            ->method('remove')
            ->with('photo.jpg', 'thumb', '');

        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(false);
        $this->cache->method('getItem')->willReturn($indexItem);

        $this->resolver->remove('photo.jpg', 'thumb', '');
    }

    #[Test]
    public function removeClearsIndexedCacheEntries(): void
    {
        $cacheKey = $this->resolver->generateCacheKey('photo.jpg', 'thumb');

        $indexItem = $this->createMock(CacheItemInterface::class);
        $indexItem->method('isHit')->willReturn(true);
        $indexItem->method('get')->willReturn([$cacheKey]);
        $indexItem->method('set')->willReturnSelf();

        $this->cache->method('getItem')->willReturn($indexItem);
        // deleteItem is called twice: once for $cacheKey, once for $indexKey (when index becomes empty)
        $this->cache->expects($this->exactly(2))
            ->method('deleteItem');

        $this->innerResolver->method('remove');

        $this->resolver->remove('photo.jpg', 'thumb', '');
    }

    #[Test]
    public function generateCacheKeyIsConsistentForSameInput(): void
    {
        $key1 = $this->resolver->generateCacheKey('photo.jpg', 'thumb');
        $key2 = $this->resolver->generateCacheKey('photo.jpg', 'thumb');

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function generateCacheKeyDiffersForDifferentFilters(): void
    {
        $key1 = $this->resolver->generateCacheKey('photo.jpg', 'thumb');
        $key2 = $this->resolver->generateCacheKey('photo.jpg', 'large');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateCacheKeyDiffersForDifferentPaths(): void
    {
        $key1 = $this->resolver->generateCacheKey('photo.jpg', 'thumb');
        $key2 = $this->resolver->generateCacheKey('other.jpg', 'thumb');

        self::assertNotSame($key1, $key2);
    }
}
