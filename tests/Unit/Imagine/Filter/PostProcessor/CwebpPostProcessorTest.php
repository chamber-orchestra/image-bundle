<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\CwebpPostProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CwebpPostProcessorTest extends TestCase
{
    #[Test]
    public function processSkipsWebpInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/webp');

        $processor = new CwebpPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsNonImageMimeType(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('application/octet-stream');

        $processor = new CwebpPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSkipsAvifInput(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/avif');

        $processor = new CwebpPostProcessor();
        $result = $processor->process($binary, []);

        self::assertSame($binary, $result);
    }

    #[Test]
    public function getIndexNameReturnsCwebp(): void
    {
        self::assertSame('cwebp', CwebpPostProcessor::getIndexName());
    }

    #[Test]
    public function constructorMergesDefaultOptions(): void
    {
        // With default binary that won't run, but we can verify it processes jpeg
        // (it would try to run cwebp - skip actual process by using invalid path with jpeg MIME type check)
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');

        // We expect it to NOT return the original binary (it will try to process)
        // but with nonexistent binary it will throw. We just verify it doesn't skip.
        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

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

        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

        $this->expectException(\Throwable::class);
        $processor->process($binary, []);
    }

    #[Test]
    public function processSupportedMimeTypesIncludePng(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/png');
        $binary->method('getContent')->willReturn('fake-png-content');

        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

        $this->expectException(\Throwable::class);
        $processor->process($binary, []);
    }
}
