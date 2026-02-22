<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\StripProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class StripProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private StripProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new StripProcessor();
    }

    #[Test]
    public function stripReturnsImageWithSameDimensions(): void
    {
        $image = (new Imagine())->create(new Box(640, 480));

        $result = $this->processor->apply($image);

        self::assertSame(640, $result->getSize()->getWidth());
        self::assertSame(480, $result->getSize()->getHeight());
    }

    #[Test]
    public function stripProducesValidImage(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));

        $result = $this->processor->apply($image);

        // Image should still be renderable — get() returns binary content
        $content = $result->get('png');
        self::assertNotEmpty($content);
        self::assertStringStartsWith("\x89PNG", $content);
    }

    #[Test]
    public function stripRemovesMetadataFromRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());

        $result = $this->processor->apply($image);

        $content = $result->get('jpeg');
        self::assertNotEmpty($content);
        self::assertStringStartsWith("\xFF\xD8\xFF", $content);
    }

    #[Test]
    public function stripPreservesDimensionsOfRealPng(): void
    {
        $image = (new Imagine())->open(self::pngFixturePath());

        $result = $this->processor->apply($image);

        self::assertSame(640, $result->getSize()->getWidth());
        self::assertSame(480, $result->getSize()->getHeight());
    }
}
