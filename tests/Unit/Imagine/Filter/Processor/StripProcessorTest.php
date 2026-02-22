<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FillProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FitProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\StripProcessor;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StripProcessorTest extends TestCase
{
    #[Test]
    public function applyCallsStripOnImage(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('strip')
            ->willReturnSelf();

        $processor = new StripProcessor();
        $result = $processor->apply($image);

        self::assertSame($image, $result);
    }

    #[Test]
    public function applyPassesConfigUnchanged(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $image->method('strip')->willReturnSelf();

        $processor = new StripProcessor();
        $config = ['some_key' => 'some_value'];
        $processor->apply($image, [], $config);

        self::assertSame('some_value', $config['some_key']);
    }

    #[Test]
    public function getIndexNameReturnsStrip(): void
    {
        self::assertSame('strip', StripProcessor::getIndexName());
    }

    #[Test]
    public function fitProcessorGetIndexNameReturnsFit(): void
    {
        self::assertSame('fit', FitProcessor::getIndexName());
    }

    #[Test]
    public function fillProcessorGetIndexNameReturnsFill(): void
    {
        self::assertSame('fill', FillProcessor::getIndexName());
    }
}
