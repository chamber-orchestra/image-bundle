<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

#[AllowMockObjectsWithoutExpectations]
class WebPathResolverTest extends TestCase
{
    private Filesystem $filesystem;
    private RequestContext $requestContext;
    private WebPathResolver $resolver;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->requestContext = new RequestContext();
        $this->resolver = new WebPathResolver(
            $this->filesystem,
            $this->requestContext,
            '/var/www/public',
            'media/cache'
        );
    }

    #[Test]
    public function resolveReturnsUrlWithPath(): void
    {
        $url = $this->resolver->resolve('images/photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/images/photo.jpg', $url);
    }

    #[Test]
    public function resolveStripsLeadingSlashFromPath(): void
    {
        $url = $this->resolver->resolve('/images/photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/images/photo.jpg', $url);
    }

    #[Test]
    public function resolveReplacesProtocolWithDashes(): void
    {
        $url = $this->resolver->resolve('s3://bucket/image.jpg', 'thumb');

        self::assertStringNotContainsString('://', $url);
        self::assertStringContainsString('s3---bucket', $url);
    }

    #[Test]
    public function storeCallsDumpFileWithCorrectPath(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getContent')->willReturn('image-data');

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with('/var/www/public/media/cache/images/photo.jpg', 'image-data');

        $this->resolver->store($binary, 'images/photo.jpg', 'thumbnail');
    }

    #[Test]
    public function removeDeletesPrefixDirectoryViaFilesystem(): void
    {
        $this->filesystem->expects($this->once())
            ->method('remove')
            ->with('/var/www/public/media/cache/Ab3xK9_z');

        $this->resolver->remove('Ab3xK9_z');
    }

    #[Test]
    public function constructorNormalizesDoubleSlashesInWebRoot(): void
    {
        $resolver = new WebPathResolver(
            $this->filesystem,
            $this->requestContext,
            '/var/www//public',
            'media'
        );

        $url = $resolver->resolve('photo.jpg', 'thumb');

        self::assertSame('/media/photo.jpg', $url);
    }

    #[Test]
    public function constructorNormalizesDoubleSlashesInPrefix(): void
    {
        $resolver = new WebPathResolver(
            $this->filesystem,
            $this->requestContext,
            '/var/www/public',
            'media//cache'
        );

        $url = $resolver->resolve('photo.jpg', 'thumb');

        self::assertSame('/media/cache/photo.jpg', $url);
    }

    #[Test]
    public function resolveIncludesBaseUrlFromRequestContext(): void
    {
        $context = new RequestContext('/app');
        $resolver = new WebPathResolver(
            $this->filesystem,
            $context,
            '/var/www/public',
            'media/cache'
        );

        $url = $resolver->resolve('images/photo.jpg', 'thumbnail');

        self::assertSame('/app/media/cache/images/photo.jpg', $url);
    }
}
