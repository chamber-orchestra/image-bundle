<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PostProcessorInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\ProcessorInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mime\MimeTypesInterface;

class FilterManagerTest extends TestCase
{
    private ImagineInterface $imagine;
    private MimeTypesInterface $mimeTypes;
    private FilterConfiguration $filterConfig;

    protected function setUp(): void
    {
        $this->imagine = $this->createMock(ImagineInterface::class);
        $this->mimeTypes = $this->createMock(MimeTypesInterface::class);
        $this->filterConfig = new FilterConfiguration([
            'sonata' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'test-secret',
                'async' => false,
                'exposed' => false,
                'output' => ['quality' => 85, 'format' => 'jpeg'],
                'processors' => ['fit' => ['width' => 800, 'height' => 600]],
                'post_processors' => [],
            ],
            'nocturne' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'test-secret',
                'async' => false,
                'exposed' => false,
                'output' => ['quality' => 90],
                'processors' => [],
                'post_processors' => ['pngquant' => ['quality' => '65-80']],
            ],
        ]);
    }

    #[Test]
    public function applyFilterMergesRuntimeConfigWithFilterConfig(): void
    {
        $binary = $this->createMockBinary('image/jpeg', 'jpeg');
        $image = $this->createMock(ImageInterface::class);
        $image->method('get')->willReturn('filtered-content');

        $this->imagine->method('load')->willReturn($image);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/jpeg']);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())
            ->method('apply')
            ->willReturnCallback(function (ImageInterface $img, array $options): ImageInterface {
                // Runtime config should override filter config
                self::assertSame(400, $options['width']);
                self::assertSame(600, $options['height']);

                return $img;
            });

        $manager = $this->createManager(['fit' => $processor]);
        $result = $manager->applyFilter($binary, 'sonata', [
            'processors' => ['fit' => ['width' => 400]],
        ]);

        self::assertInstanceOf(BinaryInterface::class, $result);
    }

    #[Test]
    public function applyCallsProcessorsInOrderAndReturnsProcessedBinary(): void
    {
        $binary = $this->createMockBinary('image/png', 'png');
        $image = $this->createMock(ImageInterface::class);
        $image->method('get')->willReturn('processed-content');

        $this->imagine->method('load')->willReturn($image);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/png']);

        $callOrder = [];

        $stripProcessor = $this->createMock(ProcessorInterface::class);
        $stripProcessor->method('apply')->willReturnCallback(function (ImageInterface $img) use (&$callOrder): ImageInterface {
            $callOrder[] = 'strip';

            return $img;
        });

        $outputProcessor = $this->createMock(ProcessorInterface::class);
        $outputProcessor->method('apply')->willReturnCallback(function (ImageInterface $img) use (&$callOrder): ImageInterface {
            $callOrder[] = 'output';

            return $img;
        });

        $this->filterConfig->set('aria', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => ['format' => 'png'],
            'processors' => ['strip' => [], 'output' => ['format' => 'png']],
            'post_processors' => [],
        ]);

        $manager = $this->createManager([
            'strip' => $stripProcessor,
            'output' => $outputProcessor,
        ]);

        $manager->applyFilter($binary, 'aria');

        self::assertSame(['strip', 'output'], $callOrder);
    }

    #[Test]
    public function unknownProcessorThrowsInvalidArgumentException(): void
    {
        $binary = $this->createMockBinary('image/jpeg', 'jpeg');
        $image = $this->createMock(ImageInterface::class);

        $this->imagine->method('load')->willReturn($image);

        $this->filterConfig->set('waltz', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => [],
            'processors' => ['nonexistent_processor' => []],
            'post_processors' => [],
        ]);

        $manager = $this->createManager([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nonexistent_processor/');

        $manager->applyFilter($binary, 'waltz');
    }

    #[Test]
    public function unknownPostProcessorThrowsInvalidArgumentException(): void
    {
        $binary = $this->createMockBinary('image/jpeg', 'jpeg');
        $image = $this->createMock(ImageInterface::class);
        $image->method('get')->willReturn('content');

        $this->imagine->method('load')->willReturn($image);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/jpeg']);

        $this->filterConfig->set('waltz', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => ['format' => 'jpeg'],
            'processors' => [],
            'post_processors' => ['nonexistent_pp' => []],
        ]);

        $manager = $this->createManager([], []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/nonexistent_pp/');

        $manager->applyFilter($binary, 'waltz');
    }

    #[Test]
    public function postProcessorsAreCalledInSequence(): void
    {
        $binary = $this->createMockBinary('image/png', 'png');
        $image = $this->createMock(ImageInterface::class);
        $image->method('get')->willReturn('png-content');

        $this->imagine->method('load')->willReturn($image);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/png']);

        $callOrder = [];

        $pp1 = $this->createMock(PostProcessorInterface::class);
        $pp1->method('process')->willReturnCallback(function (BinaryInterface $b) use (&$callOrder): BinaryInterface {
            $callOrder[] = 'pngquant';

            return new Binary('optimized-png', 'image/png', 'png');
        });

        $pp2 = $this->createMock(PostProcessorInterface::class);
        $pp2->method('process')->willReturnCallback(function (BinaryInterface $b) use (&$callOrder): BinaryInterface {
            $callOrder[] = 'cwebp';

            return new Binary('webp-output', 'image/webp', 'webp');
        });

        $this->filterConfig->set('fugue', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => ['format' => 'png'],
            'processors' => [],
            'post_processors' => ['pngquant' => [], 'cwebp' => []],
        ]);

        $manager = $this->createManager([], ['pngquant' => $pp1, 'cwebp' => $pp2]);
        $result = $manager->applyFilter($binary, 'fugue');

        self::assertSame(['pngquant', 'cwebp'], $callOrder);
        self::assertSame('webp-output', $result->getContent());
    }

    #[Test]
    public function openUsesImagineOpenForFileBinary(): void
    {
        $fileBinary = $this->createMock(FileBinaryInterface::class);
        $fileBinary->method('getPath')->willReturn('/scores/moonlight_sonata.jpg');
        $fileBinary->method('getContent')->willReturn('jpeg-content');
        $fileBinary->method('getMimeType')->willReturn('image/jpeg');
        $fileBinary->method('getFormat')->willReturn('jpeg');

        $image = $this->createMock(ImageInterface::class);
        $image->method('get')->willReturn('processed');

        $this->imagine->expects($this->once())
            ->method('open')
            ->with('/scores/moonlight_sonata.jpg')
            ->willReturn($image);
        $this->imagine->expects($this->never())->method('load');

        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/jpeg']);

        $this->filterConfig->set('prelude', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => ['format' => 'jpeg'],
            'processors' => [],
            'post_processors' => [],
        ]);

        $manager = $this->createManager([]);
        $manager->applyFilter($fileBinary, 'prelude');
    }

    #[Test]
    public function saveDeterminesFormatFromConfigFallingBackToBinaryFormat(): void
    {
        $binary = $this->createMockBinary('image/png', 'png');
        $image = $this->createMock(ImageInterface::class);
        $image->expects($this->once())
            ->method('get')
            ->with('png', $this->anything())
            ->willReturn('png-output');

        $this->imagine->method('load')->willReturn($image);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/png']);

        // No format in output config — should fall back to binary format
        $this->filterConfig->set('etude', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => [],
            'processors' => [],
            'post_processors' => [],
        ]);

        $manager = $this->createManager([]);
        $result = $manager->applyFilter($binary, 'etude');

        self::assertSame('png', $result->getFormat());
    }

    #[Test]
    public function saveThrowsWhenNoFormatCanBeDetermined(): void
    {
        $binary = $this->createMockBinary('application/octet-stream', null);
        $image = $this->createMock(ImageInterface::class);

        $this->imagine->method('load')->willReturn($image);

        $this->filterConfig->set('mystery', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => [],
            'processors' => [],
            'post_processors' => [],
        ]);

        $manager = $this->createManager([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No output format/');

        $manager->applyFilter($binary, 'mystery');
    }

    #[Test]
    public function processorReturningDifferentImageTriggersDestructOnOriginal(): void
    {
        $binary = $this->createMockBinary('image/jpeg', 'jpeg');

        $originalImage = $this->createMock(ImageInterface::class);
        $originalImage->method('get')->willReturn('jpeg-content');

        $newImage = $this->createMock(ImageInterface::class);
        $newImage->method('get')->willReturn('new-jpeg-content');

        $this->imagine->method('load')->willReturn($originalImage);
        $this->mimeTypes->method('getMimeTypes')->willReturn(['image/jpeg']);

        $processor = $this->createMock(ProcessorInterface::class);
        $processor->method('apply')->willReturn($newImage);

        $this->filterConfig->set('cadenza', [
            'resolver' => 'default',
            'loader' => 'default',
            'secret' => 'test-secret',
            'async' => false,
            'exposed' => false,
            'output' => ['format' => 'jpeg'],
            'processors' => ['fit' => ['width' => 400]],
            'post_processors' => [],
        ]);

        $manager = $this->createManager(['fit' => $processor]);
        $result = $manager->applyFilter($binary, 'cadenza');

        // Verify result came from the new image, not the original
        self::assertSame('new-jpeg-content', $result->getContent());
    }

    private function createMockBinary(string $mimeType, ?string $format): BinaryInterface
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getContent')->willReturn('raw-binary-content');
        $binary->method('getMimeType')->willReturn($mimeType);
        $binary->method('getFormat')->willReturn($format);

        return $binary;
    }

    /**
     * @param array<string, ProcessorInterface>     $processors
     * @param array<string, PostProcessorInterface> $postProcessors
     */
    private function createManager(array $processors, array $postProcessors = []): FilterManager
    {
        $processorsLocator = new ServiceLocator(\array_map(
            static fn (ProcessorInterface $p): \Closure => static fn () => $p,
            $processors,
        ));
        $postProcessorsLocator = new ServiceLocator(\array_map(
            static fn (PostProcessorInterface $pp): \Closure => static fn () => $pp,
            $postProcessors,
        ));

        return new FilterManager(
            $this->filterConfig,
            $processorsLocator,
            $postProcessorsLocator,
            $this->imagine,
            $this->mimeTypes,
        );
    }
}
