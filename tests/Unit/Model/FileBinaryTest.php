<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileBinaryTest extends TestCase
{
    #[Test]
    public function implementsFileBinaryInterface(): void
    {
        $binary = new FileBinary('/tmp/test.jpg', 'image/jpeg', 'jpeg');

        self::assertInstanceOf(FileBinaryInterface::class, $binary);
        self::assertInstanceOf(BinaryInterface::class, $binary);
    }

    #[Test]
    public function getPathReturnsPath(): void
    {
        $binary = new FileBinary('/tmp/test.jpg', 'image/jpeg', 'jpeg');

        self::assertSame('/tmp/test.jpg', $binary->getPath());
    }

    #[Test]
    public function getMimeTypeReturnsMimeType(): void
    {
        $binary = new FileBinary('/tmp/test.jpg', 'image/jpeg', 'jpeg');

        self::assertSame('image/jpeg', $binary->getMimeType());
    }

    #[Test]
    public function getFormatReturnsFormat(): void
    {
        $binary = new FileBinary('/tmp/test.jpg', 'image/jpeg', 'jpeg');

        self::assertSame('jpeg', $binary->getFormat());
    }

    #[Test]
    public function getContentReadsFile(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'image-bundle-test');
        \file_put_contents($tmpFile, 'test-image-data');

        try {
            $binary = new FileBinary($tmpFile, 'image/jpeg', 'jpeg');
            self::assertSame('test-image-data', $binary->getContent());
        } finally {
            \unlink($tmpFile);
        }
    }

    #[Test]
    public function isReadonly(): void
    {
        $ref = new \ReflectionClass(FileBinary::class);

        self::assertTrue($ref->isReadOnly());
    }
}
