<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\MozJpegPostProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MozJpegPostProcessorTest extends TestCase
{
    #[Test]
    public function processSkipsPngInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/png');

        $processor = new MozJpegPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsWebpInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/webp');

        $processor = new MozJpegPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsAvifInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/avif');

        $processor = new MozJpegPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsNonImageInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('text/html');

        $processor = new MozJpegPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function getIndexNameReturnsMozjpeg(): void
    {
        self::assertSame('mozjpeg', MozJpegPostProcessor::getIndexName());
    }

    #[Test]
    public function processAttemptsToRunForJpegMimeType(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');
        $binary->method('getContent')->willReturn('fake-jpeg-data');

        $processor = new MozJpegPostProcessor('/nonexistent/cjpeg');

        // Should attempt to process (not skip) and throw since binary doesn't exist
        $this->expectException(\Throwable::class);
        $processor->process($binary, []);
    }

    #[Test]
    public function processAttemptsToRunForImageJpgMimeType(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpg');
        $binary->method('getContent')->willReturn('fake-jpeg-data');

        $processor = new MozJpegPostProcessor('/nonexistent/cjpeg');

        // Should attempt to process (not skip) and throw since binary doesn't exist
        $this->expectException(\Throwable::class);
        $processor->process($binary, []);
    }
}
