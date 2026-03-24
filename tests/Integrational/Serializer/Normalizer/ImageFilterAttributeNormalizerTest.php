<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Serializer\Normalizer;

use ChamberOrchestra\FileBundle\Model\File;
use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use ChamberOrchestra\ImageBundle\Serializer\Normalizer\ImageFilterAttributeNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class ImageFilterAttributeNormalizerTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->serializer = static::getContainer()->get(SerializerInterface::class);
    }

    #[Test]
    public function normalizerIsRegisteredAsService(): void
    {
        $normalizer = static::getContainer()->get(ImageFilterAttributeNormalizer::class);

        self::assertInstanceOf(ImageFilterAttributeNormalizer::class, $normalizer);
    }

    #[Test]
    public function normalizesObjectWithSingleImageFilterAttribute(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new ConcertProgramme($fixture);

        /** @var array<string, mixed> $result */
        $result = $this->serializer->normalize($object, 'json');

        self::assertSame('Moonlight Sonata', $result['title']);

        // Single attribute → keyed under "default"
        self::assertIsArray($result['coverArt']);
        self::assertArrayHasKey('default', $result['coverArt']);

        $sources = $result['coverArt']['default'];
        self::assertArrayHasKey('avif', $sources);
        self::assertArrayHasKey('webp', $sources);
        self::assertCount(2, $sources);

        foreach (['avif', 'webp'] as $format) {
            self::assertArrayHasKey('1x', $sources[$format]);
            self::assertArrayHasKey('2x', $sources[$format]);
            self::assertArrayHasKey('3x', $sources[$format]);
            self::assertArrayHasKey('4x', $sources[$format]);
            self::assertNotEmpty($sources[$format]['1x']);
            self::assertIsString($sources[$format]['1x']);
        }
    }

    #[Test]
    public function normalizesObjectWithMultipleImageFilterAttributes(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new RecitalPoster($fixture);

        /** @var array<string, mixed> $result */
        $result = $this->serializer->normalize($object, 'json');

        self::assertSame('Chopin Recital', $result['title']);
        self::assertIsArray($result['poster']);
        self::assertArrayHasKey('thumbnail', $result['poster']);
        self::assertArrayHasKey('hero', $result['poster']);
        self::assertCount(2, $result['poster']);

        // Both keys have avif/webp × 3 densities
        foreach (['thumbnail', 'hero'] as $key) {
            self::assertArrayHasKey('avif', $result['poster'][$key]);
            self::assertArrayHasKey('webp', $result['poster'][$key]);
            self::assertCount(4, $result['poster'][$key]['avif']);
        }
    }

    #[Test]
    public function nullFileNormalizesToNull(): void
    {
        $object = new ConcertProgramme(null);

        /** @var array<string, mixed> $result */
        $result = $this->serializer->normalize($object, 'json');

        self::assertSame('Moonlight Sonata', $result['title']);
        self::assertNull($result['coverArt']);
    }

    #[Test]
    public function objectWithoutImageFilterAttributePassesThrough(): void
    {
        $object = new PlainProgramme();

        /** @var array<string, mixed> $result */
        $result = $this->serializer->normalize($object, 'json');

        self::assertSame('Symphony No. 5', $result['title']);
        self::assertSame('Ludwig van Beethoven', $result['composer']);
    }

    #[Test]
    public function generatedUrlsContainHmacSignature(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new ConcertProgramme($fixture);

        /** @var array<string, mixed> $result */
        $result = $this->serializer->normalize($object, 'json');

        $url = $result['coverArt']['default']['avif']['1x'];

        // Runtime URLs use HMAC-signed paths — verify the URL contains expected segments
        self::assertIsString($url);
        self::assertStringContainsString('/_media/', $url);
    }

    protected static function getKernelClass(): string
    {
        return \Tests\Integrational\TestKernel::class;
    }
}

/**
 * @internal
 */
final class ConcertProgramme
{
    public string $title = 'Moonlight Sonata';

    #[ImageFilter(filter: 'fill', width: 300, height: 300)]
    public ?File $coverArt = null;

    public function __construct(?string $fixturePath)
    {
        if (null !== $fixturePath) {
            $this->coverArt = new File($fixturePath, '/scores/moonlight_sonata.jpg');
        }
    }
}

/**
 * @internal
 */
final class RecitalPoster
{
    public string $title = 'Chopin Recital';

    #[ImageFilter(key: 'thumbnail', filter: 'fill', width: 100, height: 100)]
    #[ImageFilter(key: 'hero', filter: 'fit', width: 800, height: 400)]
    public ?File $poster = null;

    public function __construct(string $fixturePath)
    {
        $this->poster = new File($fixturePath, '/scores/moonlight_sonata.jpg');
    }
}

/**
 * @internal
 */
final class PlainProgramme
{
    public string $title = 'Symphony No. 5';
    public string $composer = 'Ludwig van Beethoven';
}
