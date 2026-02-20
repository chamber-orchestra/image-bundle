<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Filter\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\InterlaceProcessor;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class InterlaceProcessorTest extends TestCase
{
    private InterlaceProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new InterlaceProcessor();
    }

    #[Test]
    public function applyDefaultsToInterlaceLine(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('interlace')
            ->with(ImageInterface::INTERLACE_LINE)
            ->willReturnSelf();

        $result = $this->processor->apply($image);

        self::assertSame($image, $result);
    }

    #[Test]
    public function applyUsesExplicitMode(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('interlace')
            ->with(ImageInterface::INTERLACE_PLANE)
            ->willReturnSelf();

        $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_PLANE]);
    }

    #[Test]
    public function applyWithNoneModeCallsNone(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('interlace')
            ->with(ImageInterface::INTERLACE_NONE)
            ->willReturnSelf();

        $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_NONE]);
    }

    #[Test]
    public function applyWithPartitionModeCallsPartition(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('interlace')
            ->with(ImageInterface::INTERLACE_PARTITION)
            ->willReturnSelf();

        $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_PARTITION]);
    }

    #[Test]
    public function applyThrowsInvalidArgumentExceptionForUnknownMode(): void
    {
        $image = $this->createMock(ImageInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid interlace mode/');

        $this->processor->apply($image, ['mode' => 'bogus_mode']);
    }

    #[Test]
    public function applyPassesConfigByReference(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->method('interlace')->willReturnSelf();

        $config = ['existing' => 'value'];
        $this->processor->apply($image, [], $config);

        self::assertSame('value', $config['existing']);
    }

    #[Test]
    public function getIndexNameReturnsInterlace(): void
    {
        self::assertSame('interlace', InterlaceProcessor::getIndexName());
    }
}
