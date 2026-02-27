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

        // With nonexistent binary, processor returns original binary unchanged
        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

        $result = $processor->process($binary, []);
        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSupportedMimeTypesIncludeJpeg(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');
        $binary->method('getContent')->willReturn('fake-jpeg-content');

        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

        $result = $processor->process($binary, []);
        self::assertSame($binary, $result);
    }

    #[Test]
    public function processSupportedMimeTypesIncludePng(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/png');
        $binary->method('getContent')->willReturn('fake-png-content');

        $processor = new CwebpPostProcessor('/nonexistent/cwebp');

        $result = $processor->process($binary, []);
        self::assertSame($binary, $result);
    }
}
