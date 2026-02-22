<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PngquantPostProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PngquantPostProcessorTest extends TestCase
{
    #[Test]
    public function processSkipsJpegInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');

        $processor = new PngquantPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsWebpInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/webp');

        $processor = new PngquantPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsGifInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/gif');

        $processor = new PngquantPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processAttemptsToRunForPngMimeType(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/png');
        $binary->method('getContent')->willReturn('fake-png-data');

        $processor = new PngquantPostProcessor('/nonexistent/pngquant');

        // Should attempt to process (not skip) and throw since binary doesn't exist
        $this->expectException(\Throwable::class);
        $processor->process($binary, []);
    }

    #[Test]
    public function getIndexNameReturnsPngquant(): void
    {
        self::assertSame('pngquant', PngquantPostProcessor::getIndexName());
    }
}
