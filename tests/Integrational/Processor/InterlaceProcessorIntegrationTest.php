<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\InterlaceProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class InterlaceProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private InterlaceProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new InterlaceProcessor();
    }

    #[Test]
    public function interlaceAppliesLineModeByDefault(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));

        $result = $this->processor->apply($image);

        self::assertSame(100, $result->getSize()->getWidth());
        self::assertSame(100, $result->getSize()->getHeight());
    }

    #[Test]
    public function interlaceAppliesExplicitMode(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));

        $result = $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_PLANE]);

        self::assertSame(100, $result->getSize()->getWidth());
    }

    #[Test]
    public function interlaceRejectsInvalidMode(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid interlace mode/');

        $this->processor->apply($image, ['mode' => 'invalid']);
    }

    #[Test]
    public function interlacePreservesImageDimensions(): void
    {
        $image = (new Imagine())->create(new Box(640, 480));

        $result = $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_NONE]);

        self::assertSame(640, $result->getSize()->getWidth());
        self::assertSame(480, $result->getSize()->getHeight());
    }

    #[Test]
    public function interlaceAppliesLineToRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());

        $result = $this->processor->apply($image, ['mode' => ImageInterface::INTERLACE_LINE]);

        self::assertSame(800, $result->getSize()->getWidth());
        self::assertSame(600, $result->getSize()->getHeight());

        $content = $result->get('jpeg');
        self::assertNotEmpty($content);
        self::assertStringStartsWith("\xFF\xD8\xFF", $content);
    }
}
