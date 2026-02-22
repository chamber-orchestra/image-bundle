<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\OutputProcessor;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Tests\Integrational\FixtureImageTrait;

class OutputProcessorIntegrationTest extends TestCase
{
    use FixtureImageTrait;
    private OutputProcessor $processor;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required');
        }

        $this->processor = new OutputProcessor(new Imagine());
    }

    #[Test]
    public function outputSetsFormatInConfig(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = [];

        $this->processor->apply($image, ['format' => 'webp'], $config);

        self::assertSame('webp', $config['format']);
    }

    #[Test]
    public function outputSetsAvifFormatInConfig(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = [];

        $this->processor->apply($image, ['format' => 'avif'], $config);

        self::assertSame('avif', $config['format']);
    }

    #[Test]
    public function outputSetsQualityInConfig(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = [];

        $this->processor->apply($image, ['quality' => 85], $config);

        self::assertSame(85, $config['quality']);
    }

    #[Test]
    public function outputPreservesExistingConfigWhenNullOptions(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = ['format' => 'png', 'quality' => 90];

        $this->processor->apply($image, [], $config);

        self::assertSame('png', $config['format']);
        self::assertSame(90, $config['quality']);
    }

    #[Test]
    public function outputRejectsInvalidFormat(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = [];

        $this->expectException(InvalidOptionsException::class);

        $this->processor->apply($image, ['format' => 'invalid'], $config);
    }

    #[Test]
    public function outputRejectsInvalidQuality(): void
    {
        $image = (new Imagine())->create(new Box(100, 100));
        $config = [];

        $this->expectException(InvalidOptionsException::class);

        $this->processor->apply($image, ['quality' => 0], $config);
    }

    #[Test]
    public function outputDoesNotModifyImageDimensions(): void
    {
        $image = (new Imagine())->create(new Box(640, 480));
        $config = [];

        $result = $this->processor->apply($image, ['format' => 'jpg', 'quality' => 75], $config);

        self::assertSame(640, $result->getSize()->getWidth());
        self::assertSame(480, $result->getSize()->getHeight());
    }

    #[Test]
    public function outputSetsFormatOnRealJpeg(): void
    {
        $image = (new Imagine())->open(self::jpegFixturePath());
        $config = [];

        $this->processor->apply($image, ['format' => 'webp'], $config);

        self::assertSame('webp', $config['format']);
    }

    #[Test]
    public function outputSetsQualityOnRealPng(): void
    {
        $image = (new Imagine())->open(self::pngFixturePath());
        $config = [];

        $this->processor->apply($image, ['quality' => 75], $config);

        self::assertSame(75, $config['quality']);
    }
}
