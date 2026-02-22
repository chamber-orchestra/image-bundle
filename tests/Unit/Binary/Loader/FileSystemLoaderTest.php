<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\FileSystemLoader;
use ChamberOrchestra\ImageBundle\Binary\Locator\LocatorInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypesInterface;

#[AllowMockObjectsWithoutExpectations]
class FileSystemLoaderTest extends TestCase
{
    private MimeTypesInterface $mimeTypes;
    private LocatorInterface $locator;

    protected function setUp(): void
    {
        $this->mimeTypes = $this->createMock(MimeTypesInterface::class);
        $this->locator = $this->createMock(LocatorInterface::class);
        $this->locator->method('setOptions');
    }

    #[Test]
    public function findReturnsFileBinaryWithFirstExtensionAsFormat(): void
    {
        $this->locator->method('locate')->willReturn('/var/www/images/photo.jpg');
        $this->mimeTypes->method('guessMimeType')->willReturn('image/jpeg');
        $this->mimeTypes->method('getExtensions')->willReturn(['jpeg', 'jpg']);

        $binary = $this->makeLoader()->find('images/photo.jpg');

        self::assertInstanceOf(FileBinary::class, $binary);
        self::assertSame('/var/www/images/photo.jpg', $binary->getPath());
        self::assertSame('image/jpeg', $binary->getMimeType());
        self::assertSame('jpeg', $binary->getFormat());
    }

    #[Test]
    public function findUsesNullFormatWhenNoExtensionsRegistered(): void
    {
        $this->locator->method('locate')->willReturn('/var/www/images/photo.avif');
        $this->mimeTypes->method('guessMimeType')->willReturn('image/avif');
        $this->mimeTypes->method('getExtensions')->willReturn([]);

        $binary = $this->makeLoader()->find('images/photo.avif');

        self::assertNull($binary->getFormat());
        self::assertSame('image/avif', $binary->getMimeType());
    }

    #[Test]
    public function findThrowsNotLoadableExceptionWhenMimeIsNull(): void
    {
        $this->locator->method('locate')->willReturn('/var/www/images/unknown.bin');
        $this->mimeTypes->method('guessMimeType')->willReturn(null);

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/MIME type/');

        $this->makeLoader()->find('images/unknown.bin');
    }

    #[Test]
    public function constructorPassesDataRootsToLocator(): void
    {
        $locator = $this->createMock(LocatorInterface::class);
        $locator->expects($this->once())
            ->method('setOptions')
            ->with(['roots' => ['/var/www/public', '/var/www/uploads']]);

        new FileSystemLoader($this->mimeTypes, $locator, ['/var/www/public', '/var/www/uploads']);
    }

    #[Test]
    public function findDelegatesToLocatorForPathResolution(): void
    {
        $this->locator->expects($this->once())
            ->method('locate')
            ->with('subdir/photo.png')
            ->willReturn('/resolved/subdir/photo.png');

        $this->mimeTypes->method('guessMimeType')->willReturn('image/png');
        $this->mimeTypes->method('getExtensions')->willReturn(['png']);

        $binary = $this->makeLoader()->find('subdir/photo.png');

        self::assertSame('/resolved/subdir/photo.png', $binary->getPath());
    }

    private function makeLoader(): FileSystemLoader
    {
        return new FileSystemLoader($this->mimeTypes, $this->locator);
    }
}
