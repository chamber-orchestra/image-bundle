<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\CacheResolver;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

class CacheResolverIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private ArrayAdapter $cache;
    private WebPathResolver $innerResolver;
    private CacheResolver $resolver;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_cache_resolver_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $this->cache = new ArrayAdapter();
        $this->innerResolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $this->resolver = new CacheResolver($this->cache, $this->innerResolver);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function resolvePopulatesCacheAndServesFromCacheOnSecondCall(): void
    {
        $binary = new Binary('aria-image-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'scores/moonlight.jpg', 'aria');

        $url1 = $this->resolver->resolve('scores/moonlight.jpg', 'aria');
        $url2 = $this->resolver->resolve('scores/moonlight.jpg', 'aria');

        self::assertSame($url1, $url2);
        self::assertStringContainsString('media/cache', $url1);
    }

    #[Test]
    public function isStoredReturnsFalseWhenNothingStored(): void
    {
        self::assertFalse($this->resolver->isStored('scores/concerto.png', 'nocturne'));
    }

    #[Test]
    public function isStoredReturnsTrueAfterStoreViaResolver(): void
    {
        $binary = new Binary('nocturne-image-data', 'image/png', 'png');
        $this->resolver->store($binary, 'scores/concerto.png', 'nocturne');

        self::assertTrue($this->resolver->isStored('scores/concerto.png', 'nocturne'));
    }

    #[Test]
    public function storeDelegatesToInnerResolver(): void
    {
        $binary = new Binary('sonata-image-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'recordings/symphony.jpg', 'sonata');

        $expectedPath = $this->webRoot.'/media/cache/recordings/symphony.jpg';
        self::assertFileExists($expectedPath);
        self::assertSame('sonata-image-data', \file_get_contents($expectedPath));
    }

    #[Test]
    public function removeDeletesCacheAndInnerFiles(): void
    {
        $binary = new Binary('ballad-image-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'scores/waltz.jpg', 'ballad');

        // Populate the cache by resolving
        $this->resolver->resolve('scores/waltz.jpg', 'ballad');
        self::assertTrue($this->resolver->isStored('scores/waltz.jpg', 'ballad'));

        // Remove by prefix
        $this->resolver->remove('scores');

        // Inner file should be gone
        $expectedPath = $this->webRoot.'/media/cache/scores/waltz.jpg';
        self::assertFileDoesNotExist($expectedPath);

        // Cache entry should be gone — isStored checks cache first, then inner
        self::assertFalse($this->cache->hasItem(
            $this->resolver->generateCacheKey('scores/waltz.jpg', 'ballad'),
        ));
    }

    #[Test]
    public function removeWithMultiplePathsUnderSamePrefix(): void
    {
        $binary1 = new Binary('data-1', 'image/jpeg', 'jpeg');
        $binary2 = new Binary('data-2', 'image/png', 'png');

        $this->resolver->store($binary1, 'scores/prelude.jpg', 'aria');
        $this->resolver->store($binary2, 'scores/fugue.png', 'aria');

        // Populate cache
        $this->resolver->resolve('scores/prelude.jpg', 'aria');
        $this->resolver->resolve('scores/fugue.png', 'aria');

        $this->resolver->remove('scores');

        self::assertFalse($this->cache->hasItem(
            $this->resolver->generateCacheKey('scores/prelude.jpg', 'aria'),
        ));
        self::assertFalse($this->cache->hasItem(
            $this->resolver->generateCacheKey('scores/fugue.png', 'aria'),
        ));
    }

    #[Test]
    public function cacheServesResolvedUrlWithoutHittingFilesystem(): void
    {
        $binary = new Binary('etude-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'scores/etude.jpg', 'aria');

        // First resolve populates cache
        $url = $this->resolver->resolve('scores/etude.jpg', 'aria');

        // Delete file from disk directly
        $filePath = $this->webRoot.'/media/cache/scores/etude.jpg';
        self::assertFileExists($filePath);
        \unlink($filePath);
        self::assertFileDoesNotExist($filePath);

        // Second resolve should still return cached URL
        $cachedUrl = $this->resolver->resolve('scores/etude.jpg', 'aria');
        self::assertSame($url, $cachedUrl);
    }
}
