<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Binary\Loader;

use ChamberOrchestra\FileBundle\Storage\StorageInterface;
use ChamberOrchestra\ImageBundle\Binary\Loader\S3Loader;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypesInterface;

#[AllowMockObjectsWithoutExpectations]
class S3LoaderTest extends TestCase
{
    private StorageInterface $storage;
    private MimeTypesInterface $mimeTypes;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->mimeTypes = $this->createMock(MimeTypesInterface::class);
    }

    #[Test]
    public function findReturnsBinaryWithCorrectMimeType(): void
    {
        $this->storage->expects($this->once())
            ->method('download')
            ->with('scores/moonlight_sonata.jpg', $this->callback(function (string $tempFile): bool {
                \file_put_contents($tempFile, 'fake-image-content');

                return true;
            }));

        $this->mimeTypes->method('guessMimeType')->willReturn('image/jpeg');
        $this->mimeTypes->expects($this->once())->method('getExtensions')->with('image/jpeg')->willReturn(['jpeg', 'jpg']);

        $binary = $this->makeLoader()->find('scores/moonlight_sonata.jpg');

        self::assertInstanceOf(Binary::class, $binary);
        self::assertSame('fake-image-content', $binary->getContent());
        self::assertSame('image/jpeg', $binary->getMimeType());
        self::assertSame('jpeg', $binary->getFormat());
    }

    #[Test]
    public function findUsesNullFormatWhenNoExtensionsRegistered(): void
    {
        $this->storage->method('download')
            ->willReturnCallback(function (string $path, string $tempFile): void {
                \file_put_contents($tempFile, 'avif-content');
            });

        $this->mimeTypes->method('guessMimeType')->willReturn('image/avif');
        $this->mimeTypes->method('getExtensions')->willReturn([]);

        $binary = $this->makeLoader()->find('recordings/symphony_no_5.avif');

        self::assertNull($binary->getFormat());
        self::assertSame('image/avif', $binary->getMimeType());
    }

    #[Test]
    public function findThrowsNotLoadableExceptionOnStorageFailure(): void
    {
        $this->storage->method('download')
            ->willThrowException(new \RuntimeException('S3 bucket not found'));

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/could not be loaded from S3/i');

        $this->makeLoader()->find('scores/missing_concerto.jpg');
    }

    #[Test]
    public function findThrowsNotLoadableExceptionWhenMimeIsNull(): void
    {
        $this->storage->method('download')
            ->willReturnCallback(function (string $path, string $tempFile): void {
                \file_put_contents($tempFile, 'unknown-content');
            });

        $this->mimeTypes->method('guessMimeType')->willReturn(null);

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/MIME type/');

        $this->makeLoader()->find('scores/unknown_piece.bin');
    }

    #[Test]
    public function findUsesContentBasedMimeWithoutExtensionFallback(): void
    {
        $this->storage->method('download')
            ->willReturnCallback(function (string $path, string $tempFile): void {
                \file_put_contents($tempFile, 'image-bytes');
            });

        $this->mimeTypes->method('guessMimeType')->willReturn('application/octet-stream');
        $this->mimeTypes->expects($this->once())->method('getExtensions')->with('application/octet-stream')->willReturn([]);

        $binary = $this->makeLoader()->find('scores/violin_concerto.png');

        self::assertSame('application/octet-stream', $binary->getMimeType());
    }

    #[Test]
    public function findCleansTempFileAfterRead(): void
    {
        $capturedTempFile = null;

        $this->storage->method('download')
            ->willReturnCallback(function (string $path, string $tempFile) use (&$capturedTempFile): void {
                $capturedTempFile = $tempFile;
                \file_put_contents($tempFile, 'temporary-content');
            });

        $this->mimeTypes->method('guessMimeType')->willReturn('image/jpeg');
        $this->mimeTypes->method('getExtensions')->willReturn(['jpeg']);

        $this->makeLoader()->find('scores/nocturne.jpg');

        self::assertNotNull($capturedTempFile);
        self::assertFileDoesNotExist($capturedTempFile);
    }

    #[Test]
    public function findCleansTempFileEvenOnFailure(): void
    {
        $capturedTempFile = null;

        $this->storage->method('download')
            ->willReturnCallback(function (string $path, string $tempFile) use (&$capturedTempFile): void {
                $capturedTempFile = $tempFile;
                \file_put_contents($tempFile, 'temporary-content');
            });

        $this->mimeTypes->method('guessMimeType')->willReturn(null);

        try {
            $this->makeLoader()->find('scores/broken_piece.jpg');
        } catch (NotLoadableException) {
            // expected
        }

        self::assertNotNull($capturedTempFile);
        self::assertFileDoesNotExist($capturedTempFile);
    }

    #[Test]
    public function getNameReturnsS3(): void
    {
        self::assertSame('s3', $this->makeLoader()->getName());
    }

    private function makeLoader(): S3Loader
    {
        return new S3Loader($this->storage, $this->mimeTypes);
    }
}
