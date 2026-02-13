<?php

declare(strict_types=1);

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
    public function resolveReturnsUrlWithFilterAndPath(): void
    {
        $url = $this->resolver->resolve('images/photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/thumbnail/images/photo.jpg', $url);
    }

    #[Test]
    public function resolveStripsLeadingSlashFromPath(): void
    {
        $url = $this->resolver->resolve('/images/photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/thumbnail/images/photo.jpg', $url);
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
            ->with('/var/www/public/media/cache/thumbnail/images/photo.jpg', 'image-data');

        $this->resolver->store($binary, 'images/photo.jpg', 'thumbnail');
    }

    #[Test]
    public function removeDeletesFileViaFilesystem(): void
    {
        $this->filesystem->expects($this->once())
            ->method('remove')
            ->with('/var/www/public/media/cache/thumbnail/images/photo.jpg');

        $this->resolver->remove('images/photo.jpg', 'thumbnail', '');
    }

    #[Test]
    public function removeAlsoDeletesRuntimeDirWhenNonEmpty(): void
    {
        $this->filesystem->expects($this->exactly(2))
            ->method('remove');

        $this->resolver->remove('images/photo.jpg', 'thumbnail', 'rc/abc123');
    }

    #[Test]
    public function removeSkipsRuntimeDirWhenEmpty(): void
    {
        $this->filesystem->expects($this->once())
            ->method('remove');

        $this->resolver->remove('images/photo.jpg', 'thumbnail', '');
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

        self::assertSame('/media/thumb/photo.jpg', $url);
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

        self::assertSame('/media/cache/thumb/photo.jpg', $url);
    }
}
