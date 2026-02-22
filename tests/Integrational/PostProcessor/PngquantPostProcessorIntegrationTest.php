<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\PostProcessor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PngquantPostProcessor;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class PngquantPostProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private string $bin;

    protected function setUp(): void
    {
        $bin = \getenv('PNGQUANT_BIN') ?: \trim((string) \shell_exec('which pngquant 2>/dev/null'));
        if ('' === $bin || !\is_executable($bin)) {
            self::markTestSkipped('pngquant binary not found — set PNGQUANT_BIN env var');
        }

        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->bin = $bin;
    }

    #[Test]
    public function processInMemoryBinaryFromPng(): void
    {
        $content = $this->createPngContent();
        $binary = new Binary($content, 'image/png', 'png');

        $processor = new PngquantPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/png', $result->getMimeType());
        self::assertSame('png', $result->getFormat());
        self::assertNotEmpty($result->getContent());
        self::assertStringStartsWith("\x89PNG", $result->getContent());
    }

    #[Test]
    public function processSkipsUnsupportedMimeType(): void
    {
        $binary = new Binary('fake-jpeg-content', 'image/jpeg', 'jpeg');

        $processor = new PngquantPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processRealPngFile(): void
    {
        $content = \file_get_contents(self::pngFixturePath());
        $binary = new Binary($content, 'image/png', 'png');

        $processor = new PngquantPostProcessor($this->bin);
        $result = $processor->process($binary, []);

        self::assertSame('image/png', $result->getMimeType());
        self::assertSame('png', $result->getFormat());
        self::assertNotEmpty($result->getContent());
        self::assertStringStartsWith("\x89PNG", $result->getContent());
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
