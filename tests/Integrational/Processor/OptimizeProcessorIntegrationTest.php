<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\OptimizeProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Integrational\FixtureImageTrait;

class OptimizeProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private OptimizeProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new OptimizeProcessor(new Imagine());
    }

    #[Test]
    public function optimizeDownscalesLargerImage(): void
    {
        $image = (new Imagine())->create(new Box(2000, 1000));

        $result = $this->processor->apply($image, ['width' => 800, 'height' => 0]);

        self::assertSame(800, $result->getSize()->getWidth());
        self::assertSame(400, $result->getSize()->getHeight());
    }

    #[Test]
    public function optimizeDoesNotUpscaleSmallerImage(): void
    {
        $image = (new Imagine())->create(new Box(400, 200));

        $result = $this->processor->apply($image, ['width' => 800, 'height' => 0]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(200, $result->getSize()->getHeight());
    }

    #[Test]
    public function optimizeWithDensity2ScalesOutput(): void
    {
        $image = (new Imagine())->create(new Box(2000, 1000));

        $result = $this->processor->apply($image, ['width' => 500, 'height' => 0, 'density' => 2]);

        self::assertSame(1000, $result->getSize()->getWidth());
        self::assertSame(500, $result->getSize()->getHeight());
    }

    #[Test]
    public function optimizePreservesAspectRatio(): void
    {
        $image = (new Imagine())->create(new Box(1600, 900));

        $result = $this->processor->apply($image, ['width' => 800, 'height' => 0]);

        self::assertSame(800, $result->getSize()->getWidth());
        self::assertSame(450, $result->getSize()->getHeight());
    }

    #[Test]
    public function optimizeDownscalesRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());

        $result = $this->processor->apply($image, ['width' => 400, 'height' => 0]);

        self::assertSame(400, $result->getSize()->getWidth());
        self::assertSame(300, $result->getSize()->getHeight());
    }

    #[Test]
    public function optimizeDoesNotUpscaleRealPng(): void
    {
        $image = (new Imagine())->open(self::pngFixturePath());

        $result = $this->processor->apply($image, ['width' => 1024, 'height' => 0]);

        self::assertSame(640, $result->getSize()->getWidth());
        self::assertSame(480, $result->getSize()->getHeight());
    }
}
