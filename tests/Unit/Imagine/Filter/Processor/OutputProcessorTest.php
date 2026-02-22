<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter\Processor;

use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\OutputProcessor;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[AllowMockObjectsWithoutExpectations]
class OutputProcessorTest extends TestCase
{
    private ImagineInterface $imagine;

    protected function setUp(): void
    {
        $this->imagine = $this->createMock(ImagineInterface::class);
    }

    #[Test]
    public function applyWithNoOptionsReturnsImageAndSetsNullsInConfig(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $config = [];
        $result = $processor->apply($image, [], $config);

        self::assertSame($image, $result);
        self::assertNull($config['format']);
        self::assertNull($config['quality']);
    }

    #[Test]
    public function applyWithQualitySetsConfigQuality(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $config = [];
        $processor->apply($image, ['quality' => 80], $config);

        self::assertSame(80, $config['quality']);
    }

    #[Test]
    public function applyDoesNotOverrideExistingConfigValuesWhenOptionsAreNull(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $config = ['format' => 'jpeg', 'quality' => 90];
        $processor->apply($image, [], $config);

        self::assertSame('jpeg', $config['format']);
        self::assertSame(90, $config['quality']);
    }

    #[Test]
    public function applyThrowsForQualityOfZero(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $this->expectException(InvalidOptionsException::class);

        $processor->apply($image, ['quality' => 0]);
    }

    #[Test]
    public function applyThrowsForQualityOver100(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $this->expectException(InvalidOptionsException::class);

        $processor->apply($image, ['quality' => 101]);
    }

    #[Test]
    public function applyAcceptsValidFormatForAnyDriver(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $config = [];
        $result = $processor->apply($image, ['format' => 'webp'], $config);

        self::assertSame($image, $result);
        self::assertSame('webp', $config['format']);
    }

    #[Test]
    public function applyThrowsForInvalidFormat(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $this->expectException(InvalidOptionsException::class);

        $processor->apply($image, ['format' => 'invalid_format']);
    }

    #[Test]
    public function applyAcceptsNullFormat(): void
    {
        $image = $this->createMock(ImageInterface::class);
        $processor = new OutputProcessor($this->imagine);

        $config = [];
        $result = $processor->apply($image, ['format' => null], $config);

        self::assertSame($image, $result);
        self::assertNull($config['format']);
    }

    #[Test]
    public function getIndexNameReturnsOutput(): void
    {
        self::assertSame('output', OutputProcessor::getIndexName());
    }
}
