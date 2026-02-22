<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FillProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class FillProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private FillProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new FillProcessor(new Imagine());
    }

    #[Test]
    public function fillCropsLargerImageToExactDimensions(): void
    {
        $image = (new Imagine())->create(new Box(800, 600));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 300]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillPlacesSmallerImageOnCanvasOfTargetSize(): void
    {
        $image = (new Imagine())->create(new Box(200, 150));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 300]);

        // Image is not stretched, but output matches target (padded with background)
        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillNonProportionalImageCropsToExactBox(): void
    {
        $image = (new Imagine())->create(new Box(1000, 500));

        $result = $this->processor->apply($image, ['width' => 300, 'height' => 300]);

        self::assertSame(300, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillWithDensity2ProducesDoubleSize(): void
    {
        $image = (new Imagine())->create(new Box(800, 600));

        $result = $this->processor->apply($image, ['width' => 200, 'height' => 150, 'density' => 2]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillWithWidthOnlyCalculatesHeight(): void
    {
        $image = (new Imagine())->create(new Box(800, 400));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 0]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(200, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillCropsRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 400]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(400, $result->getSize()->getHeight());
    }

    #[Test]
    public function fillCropsRealPng(): void
    {
        $image = (new Imagine())->open(self::pngFixturePath());

        $result = $this->processor->apply($image, ['width' => 300, 'height' => 300]);

        self::assertSame(300, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }
}
