<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Serializer\Normalizer;

use ChamberOrchestra\FileBundle\Model\File;
use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use ChamberOrchestra\ImageBundle\Serializer\Normalizer\ImageFilterAttributeNormalizer;
use ChamberOrchestra\ImageBundle\Twig\ImageRuntime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ImageFilterAttributeNormalizerTest extends TestCase
{
    private ImageRuntime $imageRuntime;
    private ImageFilterAttributeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->imageRuntime = $this->createMock(ImageRuntime::class);
        $this->normalizer = new ImageFilterAttributeNormalizer($this->imageRuntime);

        $parentNormalizer = $this->createMock(NormalizerInterface::class);
        $parentNormalizer->method('normalize')->willReturnCallback(
            static fn (mixed $object): array => ['title' => $object->title ?? ''],
        );
        $this->normalizer->setNormalizer($parentNormalizer);
    }

    #[Test]
    public function supportsObjectWithImageFilterAttribute(): void
    {
        $object = $this->createObjectWithSingleAttribute();

        self::assertTrue($this->normalizer->supportsNormalization($object));
    }

    #[Test]
    public function doesNotSupportObjectWithoutAttributes(): void
    {
        $object = new class {
            public string $title = 'Nocturne in E-flat';
        };

        self::assertFalse($this->normalizer->supportsNormalization($object));
    }

    #[Test]
    public function doesNotSupportWhenAlreadyProcessing(): void
    {
        $object = $this->createObjectWithSingleAttribute();

        self::assertFalse(
            $this->normalizer->supportsNormalization($object, context: [ImageFilterAttributeNormalizer::class => true]),
        );
    }

    #[Test]
    public function doesNotSupportNonObjects(): void
    {
        self::assertFalse($this->normalizer->supportsNormalization('not an object'));
        self::assertFalse($this->normalizer->supportsNormalization(42));
    }

    #[Test]
    public function supportsCacheReturnsSameResultForSameClass(): void
    {
        $object = $this->createObjectWithSingleAttribute();

        // First call populates cache
        $first = $this->normalizer->supportsNormalization($object);
        // Second call hits cache
        $second = $this->normalizer->supportsNormalization($object);

        self::assertSame($first, $second);
    }

    #[Test]
    public function nullFilePropertyNormalizesToNull(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Waltz in A minor';

            #[ImageFilter(filter: 'fill', width: 300, height: 300)]
            public ?File $coverArt = null;

            public function __construct(string $fixture)
            {
            }
        };

        $result = $this->normalizer->normalize($object);

        self::assertNull($result['coverArt']);
    }

    #[Test]
    public function nonImageFileNormalizesToNull(): void
    {
        // Use a non-image file (bootstrap.php is a PHP file, not an image)
        $path = __DIR__.'/../../../bootstrap.php';
        $object = new class($path) {
            public string $title = 'Fugue in G minor';

            #[ImageFilter(filter: 'fill', width: 200, height: 200)]
            public ?File $score = null;

            public function __construct(string $path)
            {
                $this->score = new File($path, '/scores/fugue.php');
            }
        };

        $result = $this->normalizer->normalize($object);

        self::assertNull($result['score']);
    }

    #[Test]
    public function singleAttributeGeneratesKeyedSources(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = $this->createObjectWithFile($fixture, 'scores/moonlight_sonata.jpg');

        $this->imageRuntime->method('fill')->willReturn('/cached/url');

        $result = $this->normalizer->normalize($object);

        self::assertArrayHasKey('default', $result['coverArt']);
        self::assertArrayHasKey('avif', $result['coverArt']['default']);
        self::assertArrayHasKey('webp', $result['coverArt']['default']);
        self::assertCount(2, $result['coverArt']['default']);

        foreach (['avif', 'webp'] as $format) {
            self::assertArrayHasKey('1x', $result['coverArt']['default'][$format]);
            self::assertArrayHasKey('2x', $result['coverArt']['default'][$format]);
            self::assertArrayHasKey('3x', $result['coverArt']['default'][$format]);
        }
    }

    #[Test]
    public function multipleAttributesGenerateMultipleKeys(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Piano Concerto No. 1';

            #[ImageFilter(key: 'thumbnail', filter: 'fill', width: 100, height: 100)]
            #[ImageFilter(key: 'hero', filter: 'fit', width: 800, height: 400)]
            public ?File $coverArt = null;

            public function __construct(string $path)
            {
                $this->coverArt = new File($path, '/scores/moonlight_sonata.jpg');
            }
        };

        $this->imageRuntime->method('fill')->willReturn('/cached/fill');
        $this->imageRuntime->method('fit')->willReturn('/cached/fit');

        $result = $this->normalizer->normalize($object);

        self::assertArrayHasKey('thumbnail', $result['coverArt']);
        self::assertArrayHasKey('hero', $result['coverArt']);
        self::assertCount(2, $result['coverArt']);
    }

    #[Test]
    public function fillFilterDelegatesCorrectly(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = $this->createObjectWithFile($fixture, 'scores/moonlight_sonata.jpg');

        $this->imageRuntime->expects($this->exactly(6))
            ->method('fill')
            ->willReturn('/cached/fill');

        $this->normalizer->normalize($object);
    }

    #[Test]
    public function fitFilterDelegatesCorrectly(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Etude Op. 10';

            #[ImageFilter(filter: 'fit', width: 600, height: 400)]
            public ?File $coverArt = null;

            public function __construct(string $path)
            {
                $this->coverArt = new File($path, '/scores/moonlight_sonata.jpg');
            }
        };

        $this->imageRuntime->expects($this->exactly(6))
            ->method('fit')
            ->willReturn('/cached/fit');

        $this->normalizer->normalize($object);
    }

    #[Test]
    public function optimizeFilterDelegatesCorrectly(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Prelude in C';

            #[ImageFilter(filter: 'optimize', width: 1200)]
            public ?File $coverArt = null;

            public function __construct(string $path)
            {
                $this->coverArt = new File($path, '/scores/moonlight_sonata.jpg');
            }
        };

        $this->imageRuntime->expects($this->exactly(6))
            ->method('optimize')
            ->willReturn('/cached/optimize');

        $this->normalizer->normalize($object);
    }

    #[Test]
    public function leadingSlashIsStrippedFromUri(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = $this->createObjectWithFile($fixture, '/scores/moonlight_sonata.jpg');

        $this->imageRuntime->expects($this->atLeastOnce())
            ->method('fill')
            ->with(
                'scores/moonlight_sonata.jpg',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn('/cached/url');

        $this->normalizer->normalize($object);
    }

    #[Test]
    public function presetIsPassedToRuntime(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Ballade No. 1';

            #[ImageFilter(filter: 'fill', width: 300, height: 300, preset: 'high_quality')]
            public ?File $coverArt = null;

            public function __construct(string $path)
            {
                $this->coverArt = new File($path, 'scores/moonlight_sonata.jpg');
            }
        };

        $this->imageRuntime->expects($this->atLeastOnce())
            ->method('fill')
            ->with(
                $this->anything(),
                300,
                300,
                $this->anything(),
                'high_quality',
            )
            ->willReturn('/cached/url');

        $this->normalizer->normalize($object);
    }

    #[Test]
    public function emptyUriNormalizesToNull(): void
    {
        $fixture = __DIR__.'/../../../Fixtures/moonlight_sonata.jpg';
        $object = new class($fixture) {
            public string $title = 'Impromptu in A-flat';

            #[ImageFilter(filter: 'fill', width: 200, height: 200)]
            public ?File $coverArt = null;

            public function __construct(string $path)
            {
                $this->coverArt = new File($path, '');
            }
        };

        $result = $this->normalizer->normalize($object);

        self::assertNull($result['coverArt']);
    }

    #[Test]
    public function getSupportedTypesReturnsObjectAsNonCacheable(): void
    {
        $types = $this->normalizer->getSupportedTypes(null);

        self::assertSame(['object' => false], $types);
    }

    private function createObjectWithSingleAttribute(): object
    {
        return new class {
            public string $title = 'Sonata Pathétique';

            #[ImageFilter(filter: 'fill', width: 300, height: 300)]
            public ?File $coverArt = null;
        };
    }

    private function createObjectWithFile(string $fixturePath, string $uri): object
    {
        return new class($fixturePath, $uri) {
            public string $title = 'Rondo Alla Turca';

            #[ImageFilter(filter: 'fill', width: 300, height: 300)]
            public ?File $coverArt = null;

            public function __construct(string $path, string $uri)
            {
                $this->coverArt = new File($path, $uri);
            }
        };
    }
}
