<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FitProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class FitProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private FitProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new FitProcessor(new Imagine());
    }

    #[Test]
    public function fitDownscalesLargerImagePreservingAspectRatio(): void
    {
        $image = (new Imagine())->create(new Box(800, 600));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 300]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitPlacesSmallerImageOnCanvasOfTargetSize(): void
    {
        $image = (new Imagine())->create(new Box(200, 150));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 300]);

        // Image is not stretched, but output matches target (padded with background)
        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitWithWidthOnlyCalculatesHeight(): void
    {
        $image = (new Imagine())->create(new Box(800, 400));

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 0]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(200, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitWithHeightOnlyCalculatesWidth(): void
    {
        $image = (new Imagine())->create(new Box(800, 400));

        $result = $this->processor->apply($image, ['width' => 0, 'height' => 200]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(200, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitWithDensity2ProducesDoubleSize(): void
    {
        $image = (new Imagine())->create(new Box(800, 600));

        $result = $this->processor->apply($image, ['width' => 200, 'height' => 150, 'density' => 2]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitNonProportionalImageFitsInsideBoxOnCanvas(): void
    {
        $image = (new Imagine())->create(new Box(1000, 500));

        $result = $this->processor->apply($image, ['width' => 300, 'height' => 300]);

        // 1000x500 fitted to 300x300 → resized to 300x150, centered on 300x300 canvas
        self::assertSame(300, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitDownscalesRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 300]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function fitDownscalesRealPng(): void
    {
        $image = (new Imagine())->open(self::pngFixturePath());

        $result = $this->processor->apply($image, ['width' => 320, 'height' => 240]);

        self::assertSame(320, $result->getSize()->getWidth());
        self::assertSame(240, $result->getSize()->getHeight());
    }
}
