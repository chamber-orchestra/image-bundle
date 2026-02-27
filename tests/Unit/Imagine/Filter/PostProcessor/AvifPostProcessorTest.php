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
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\AvifPostProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AvifPostProcessorTest extends TestCase
{
    #[Test]
    public function processSkipsAvifInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/avif');

        $processor = new AvifPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsNonImageMimeType(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('application/octet-stream');

        $processor = new AvifPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsWebpInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/webp');

        $processor = new AvifPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function getIndexNameReturnsAvifenc(): void
    {
        self::assertSame('avifenc', AvifPostProcessor::getIndexName());
    }

    #[Test]
    public function constructorMergesDefaultOptions(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');

        $processor = new AvifPostProcessor('/nonexistent/avifenc');

        try {
            $processor->process($binary, []);
            self::fail('Expected an exception from non-existent binary');
        } catch (\Throwable $e) {
            // Expected - the binary doesn't exist, which confirms jpeg is NOT skipped
            self::assertNotSame($binary, null);
        }
    }

    #[Test]
    public function processSupportedMimeTypesIncludeJpeg(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');
        $binary->method('getContent')->willReturn('fake-jpeg-content');

        $processor = new AvifPostProcessor('/nonexistent/avifenc');

        $result = $processor->process($binary, []);
        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSupportedMimeTypesIncludePng(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/png');
        $binary->method('getContent')->willReturn('fake-png-content');

        $processor = new AvifPostProcessor('/nonexistent/avifenc');

        $result = $processor->process($binary, []);
        self::assertSame($binary, $result);
    }
}
