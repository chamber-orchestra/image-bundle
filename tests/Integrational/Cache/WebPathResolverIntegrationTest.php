<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

class WebPathResolverIntegrationTest extends TestCase
{
    private string $webRoot;
    private WebPathResolver $resolver;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_web_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $this->resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache'
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function isStoredReturnsFalseBeforeStoring(): void
    {
        self::assertFalse($this->resolver->isStored('images/photo.jpg', 'thumbnail'));
    }

    #[Test]
    public function storeWritesFileToDisk(): void
    {
        $binary = new Binary('fake-image-data', 'image/jpeg', 'jpeg');

        $this->resolver->store($binary, 'images/photo.jpg', 'thumbnail');

        $expectedPath = $this->webRoot.'/media/cache/images/photo.jpg';
        self::assertFileExists($expectedPath);
        self::assertSame('fake-image-data', \file_get_contents($expectedPath));
    }

    #[Test]
    public function isStoredReturnsTrueAfterStoring(): void
    {
        $binary = new Binary('fake-image-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'images/photo.jpg', 'thumbnail');

        self::assertTrue($this->resolver->isStored('images/photo.jpg', 'thumbnail'));
    }

    #[Test]
    public function resolveReturnsCorrectUrl(): void
    {
        $url = $this->resolver->resolve('images/photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/images/photo.jpg', $url);
    }

    #[Test]
    public function removeDeletesStoredFile(): void
    {
        $binary = new Binary('fake-image-data', 'image/jpeg', 'jpeg');
        $this->resolver->store($binary, 'images/photo.jpg', 'thumbnail');

        $this->resolver->remove('images');

        $expectedPath = $this->webRoot.'/media/cache/images/photo.jpg';
        self::assertFileDoesNotExist($expectedPath);
    }

    #[Test]
    public function storeCreatesIntermediateDirectories(): void
    {
        $binary = new Binary('fake-image-data', 'image/jpeg', 'jpeg');

        $this->resolver->store($binary, 'deep/nested/path/photo.jpg', 'thumbnail');

        $expectedPath = $this->webRoot.'/media/cache/deep/nested/path/photo.jpg';
        self::assertFileExists($expectedPath);
    }

    #[Test]
    public function removeSilentlyIgnoresMissingFile(): void
    {
        // Should not throw when file doesn't exist
        $this->resolver->remove('nonexistent');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function differentFiltersShareSameCachePath(): void
    {
        $binary = new Binary('thumb-data', 'image/jpeg', 'jpeg');
        $largeBinary = new Binary('large-data', 'image/jpeg', 'jpeg');

        $this->resolver->store($binary, 'photo.jpg', 'thumbnail');
        $this->resolver->store($largeBinary, 'photo.jpg', 'large');

        // Since filter is no longer part of the path, both filters write to the same location
        // and the second store overwrites the first
        $cachedPath = $this->webRoot.'/media/cache/photo.jpg';
        self::assertFileExists($cachedPath);
        self::assertSame('large-data', \file_get_contents($cachedPath));
    }
}
