<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BinaryTest extends TestCase
{
    #[Test]
    public function implementsBinaryInterface(): void
    {
        $binary = new Binary('content', 'image/jpeg', 'jpeg');

        self::assertInstanceOf(BinaryInterface::class, $binary);
    }

    #[Test]
    public function getContentReturnsContent(): void
    {
        $binary = new Binary('raw-image-data', 'image/png', 'png');

        self::assertSame('raw-image-data', $binary->getContent());
    }

    #[Test]
    public function getMimeTypeReturnsMimeType(): void
    {
        $binary = new Binary('content', 'image/webp', 'webp');

        self::assertSame('image/webp', $binary->getMimeType());
    }

    #[Test]
    public function getFormatReturnsFormat(): void
    {
        $binary = new Binary('content', 'image/jpeg', 'jpeg');

        self::assertSame('jpeg', $binary->getFormat());
    }

    #[Test]
    public function isReadonly(): void
    {
        $ref = new \ReflectionClass(Binary::class);

        self::assertTrue($ref->isReadOnly());
    }
}
