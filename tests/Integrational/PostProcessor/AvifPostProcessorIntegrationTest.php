<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\PostProcessor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\AvifPostProcessor;
use ChamberOrchestra\ImageBundle\Model\Binary;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class AvifPostProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private string $bin;
    private string $tempDir;

    protected function setUp(): void
    {
        $bin = \getenv('AVIFENC_BIN') ?: \trim((string) \shell_exec('which avifenc 2>/dev/null'));
        if ('' === $bin || !\is_executable($bin)) {
            self::markTestSkipped('avifenc binary not found — set AVIFENC_BIN env var');
        }

        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->bin = $bin;
        $this->tempDir = \realpath(\sys_get_temp_dir()).'/image_bundle_avifenc_'.\uniqid();
        \mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (!isset($this->tempDir) || !\is_dir($this->tempDir)) {
            return;
        }

        foreach (\scandir($this->tempDir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            \unlink($this->tempDir.'/'.$entry);
        }

        \rmdir($this->tempDir);
    }

    #[Test]
    public function processInMemoryBinaryFromJpeg(): void
    {
        $content = $this->createJpegContent();
        $binary = new Binary($content, 'image/jpeg', 'jpeg');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processInMemoryBinaryFromPng(): void
    {
        $content = $this->createPngContent();
        $binary = new Binary($content, 'image/png', 'png');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processFileBinaryFromJpeg(): void
    {
        $path = $this->tempDir.'/violin_concerto.jpg';
        \file_put_contents($path, $this->createJpegContent());

        $binary = new FileBinary($path, 'image/jpeg', 'jpeg');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processFileBinaryFromPng(): void
    {
        $path = $this->tempDir.'/nocturne_in_e_flat.png';
        \file_put_contents($path, $this->createPngContent());

        $binary = new FileBinary($path, 'image/png', 'png');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processSkipsUnsupportedMimeType(): void
    {
        $binary = new Binary('fake-bmp-content', 'image/bmp', 'bmp');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processRealJpegFile(): void
    {
        $content = \file_get_contents(self::jpegFixturePath());
        $binary = new Binary($content, 'image/jpeg', 'jpeg');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processRealPngFile(): void
    {
        $content = \file_get_contents(self::pngFixturePath());
        $binary = new Binary($content, 'image/png', 'png');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    #[Test]
    public function processRealJpegFileBinary(): void
    {
        $binary = new FileBinary(self::jpegFixturePath(), 'image/jpeg', 'jpeg');

        $processor = new AvifPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/avif', $result->getMimeType());
        self::assertSame('avif', $result->getFormat());
        self::assertNotEmpty($result->getContent());
    }

    private function createJpegContent(): string
    {
        $img = \imagecreatetruecolor(8, 8);
        $color = \imagecolorallocate($img, 200, 100, 50);
        \imagefill($img, 0, 0, $color);
        \ob_start();
        \imagejpeg($img, null, 90);
        $content = \ob_get_clean();

        return $content;
    }

    private function createPngContent(): string
    {
        $img = \imagecreatetruecolor(8, 8);
        $color = \imagecolorallocate($img, 50, 100, 200);
        \imagefill($img, 0, 0, $color);
        \ob_start();
        \imagepng($img);
        $content = \ob_get_clean();

        return $content;
    }
}
