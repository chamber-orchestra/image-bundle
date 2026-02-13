<?php

declare(strict_types=1);

namespace Tests\Integrational\Binary;

use ChamberOrchestra\ImageBundle\Binary\Loader\FileSystemLoader;
use ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypes;

class FileSystemLoaderIntegrationTest extends TestCase
{
    private string $rootDir;
    private FileSystemLoader $loader;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/image_bundle_loader_'.uniqid();
        mkdir($this->rootDir, 0755, true);

        $this->loader = new FileSystemLoader(
            MimeTypes::getDefault(),
            new FileSystemLocator(),
            [$this->rootDir]
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    #[Test]
    public function findReturnsFileBinaryForJpeg(): void
    {
        $this->writeSmallJpeg($this->rootDir.'/photo.jpg');

        $binary = $this->loader->find('photo.jpg');

        self::assertInstanceOf(FileBinary::class, $binary);
        self::assertSame($this->rootDir.'/photo.jpg', $binary->getPath());
        self::assertStringStartsWith('image/', $binary->getMimeType());
    }

    #[Test]
    public function findReturnsCorrectMimeTypeForPng(): void
    {
        $this->writeSmallPng($this->rootDir.'/image.png');

        $binary = $this->loader->find('image.png');

        self::assertSame('image/png', $binary->getMimeType());
    }

    #[Test]
    public function findReturnsFormatAsFirstExtension(): void
    {
        $this->writeSmallPng($this->rootDir.'/image.png');

        $binary = $this->loader->find('image.png');

        self::assertNotNull($binary->getFormat());
        self::assertContains($binary->getFormat(), ['png']);
    }

    #[Test]
    public function findThrowsNotLoadableExceptionForNonExistentFile(): void
    {
        $this->expectException(NotLoadableException::class);

        $this->loader->find('nonexistent.jpg');
    }

    #[Test]
    public function findWorksWithFileInSubdirectory(): void
    {
        mkdir($this->rootDir.'/sub', 0755, true);
        $this->writeSmallPng($this->rootDir.'/sub/image.png');

        $binary = $this->loader->find('sub/image.png');

        self::assertSame($this->rootDir.'/sub/image.png', $binary->getPath());
    }

    #[Test]
    public function getContentReturnsFileBytes(): void
    {
        $this->writeSmallPng($this->rootDir.'/image.png');

        $binary = $this->loader->find('image.png');
        $content = $binary->getContent();

        self::assertNotEmpty($content);
        // PNG magic bytes: \x89PNG
        self::assertStringStartsWith("\x89PNG", $content);
    }

    private function writeSmallJpeg(string $path): void
    {
        // Minimal valid JPEG (1x1 white pixel)
        $data = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            ."\xFF\xDB\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\t\t"
            ."\x08\n\x0C\x14\r\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A"
            ."\x1F\x1E\x1D\x1A\x1C\x1C $.' \",#\x1C\x1C(7),01444\x1F'9=82<.342\x1E"
            ."\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00"
            ."\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00"
            ."\x00\x01\x02\x03\x04\x05\x06\x07\x08\t\n\x0B"
            ."\xFF\xC4\x00\xB5\x10\x00\x02\x01\x03\x03\x02\x04\x03\x05\x05\x04\x04\x00\x00\x01}"
            ."\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xFB\x00\x00\x1F\xFF\xD9";

        file_put_contents($path, $data);
    }

    private function writeSmallPng(string $path): void
    {
        // Use GD to create a minimal 1x1 PNG
        if (function_exists('imagecreate')) {
            $img = imagecreate(1, 1);
            imagecolorallocate($img, 255, 255, 255);
            ob_start();
            imagepng($img);
            $data = ob_get_clean();
            file_put_contents($path, $data);
        } else {
            // Fallback: minimal PNG header (may not pass MIME detection but tests path logic)
            file_put_contents($path, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82");
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
