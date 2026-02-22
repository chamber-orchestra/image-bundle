<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\PostProcessor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\MozJpegPostProcessor;
use ChamberOrchestra\ImageBundle\Model\Binary;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class MozJpegPostProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private string $bin;

    protected function setUp(): void
    {
        $bin = \getenv('MOZJPEG_BIN') ?: '/opt/mozjpeg/bin/cjpeg';
        if (!\is_executable($bin)) {
            $bin = \trim((string) \shell_exec('which cjpeg 2>/dev/null'));
            if ('' === $bin) {
                self::markTestSkipped('cjpeg (mozjpeg) binary not found — set MOZJPEG_BIN env var');
            }
        }

        $help = \shell_exec($bin.' -help 2>&1');
        if (!\str_contains((string) $help, 'quant-table')) {
            self::markTestSkipped('cjpeg found but is not mozjpeg (missing -quant-table support)');
        }

        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->bin = $bin;
    }

    #[Test]
    public function processInMemoryBinaryFromJpeg(): void
    {
        $content = $this->createJpegContent();
        $binary = new Binary($content, 'image/jpeg', 'jpeg');

        $processor = new MozJpegPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/jpeg', $result->getMimeType());
        self::assertSame('jpeg', $result->getFormat());
        self::assertNotEmpty($result->getContent());
        self::assertStringStartsWith("\xFF\xD8\xFF", $result->getContent());
    }

    #[Test]
    public function processSkipsUnsupportedMimeType(): void
    {
        $binary = new Binary('fake-png-content', 'image/png', 'png');

        $processor = new MozJpegPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processRealJpegFile(): void
    {
        $content = \file_get_contents(self::jpegFixturePath());
        $binary = new Binary($content, 'image/jpeg', 'jpeg');

        $processor = new MozJpegPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/jpeg', $result->getMimeType());
        self::assertSame('jpeg', $result->getFormat());
        self::assertNotEmpty($result->getContent());
        self::assertStringStartsWith("\xFF\xD8\xFF", $result->getContent());
    }

    #[Test]
    public function processRealJpegFileBinary(): void
    {
        $binary = new FileBinary(self::jpegFixturePath(), 'image/jpeg', 'jpeg');

        $processor = new MozJpegPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/jpeg', $result->getMimeType());
        self::assertSame('jpeg', $result->getFormat());
        self::assertNotEmpty($result->getContent());
        self::assertStringStartsWith("\xFF\xD8\xFF", $result->getContent());
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
}
