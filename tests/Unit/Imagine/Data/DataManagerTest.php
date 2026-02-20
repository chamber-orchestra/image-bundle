<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Data;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\BinaryMimeTypeGuesser;
use ChamberOrchestra\ImageBundle\Binary\Loader\LoaderInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypesInterface;

#[AllowMockObjectsWithoutExpectations]
class DataManagerTest extends TestCase
{
    private MimeTypesInterface $mimeTypes;
    private FilterConfiguration $filterConfig;
    private BinaryMimeTypeGuesser $contentGuesser;

    protected function setUp(): void
    {
        $this->mimeTypes = $this->createMock(MimeTypesInterface::class);
        $this->filterConfig = $this->createMock(FilterConfiguration::class);
        $this->contentGuesser = $this->createMock(BinaryMimeTypeGuesser::class);
    }

    #[Test]
    public function findReturnsBinaryWhenLoaderReturnsBinaryInterface(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');

        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('find')->willReturn($binary);

        $this->filterConfig->method('get')->willReturn(['loader' => null, 'default_image' => null]);

        $manager = $this->makeManager();
        $manager->addLoader($loader, 'default');

        $result = $manager->find('thumbnail', 'photo.jpg');

        self::assertSame($binary, $result);
    }

    #[Test]
    public function findWrapsBinaryStringInBinaryObjectUsingContentGuesser(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('find')->willReturn('raw-image-bytes');

        // contentGuesser (BinaryMimeTypeGuesser) is used for raw content — not MimeTypes
        $this->contentGuesser->method('guessMimeType')->willReturn('image/png');
        $this->mimeTypes->method('getExtensions')->willReturn(['png']);
        $this->filterConfig->method('get')->willReturn(['loader' => null, 'default_image' => null]);

        $manager = $this->makeManager();
        $manager->addLoader($loader, 'default');

        $result = $manager->find('thumbnail', 'photo.png');

        self::assertInstanceOf(BinaryInterface::class, $result);
        self::assertSame('image/png', $result->getMimeType());
    }

    #[Test]
    public function findThrowsNotLoadableWhenMimeTypeIsNotImage(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('text/html');

        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('find')->willReturn($binary);

        $this->filterConfig->method('get')->willReturn(['loader' => null, 'default_image' => null]);

        $manager = $this->makeManager();
        $manager->addLoader($loader, 'default');

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/not an image/i');

        $manager->find('thumbnail', 'document.html');
    }

    #[Test]
    public function findThrowsNotLoadableWhenContentGuesserReturnsNull(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('find')->willReturn('unidentifiable-bytes');

        $this->contentGuesser->method('guessMimeType')->willReturn(null);
        $this->filterConfig->method('get')->willReturn(['loader' => null, 'default_image' => null]);

        $manager = $this->makeManager();
        $manager->addLoader($loader, 'default');

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/could not be determined/i');

        $manager->find('thumbnail', 'photo.bin');
    }

    #[Test]
    public function getLoaderThrowsForUnknownLoader(): void
    {
        $this->filterConfig->method('get')->willReturn(['loader' => 'nonexistent', 'default_image' => null]);

        $manager = $this->makeManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Could not find data loader/');

        $manager->getLoader('thumbnail');
    }

    #[Test]
    public function addLoadersRegistersMultipleLoaders(): void
    {
        $loaderA = $this->createMock(LoaderInterface::class);
        $loaderA->method('getName')->willReturn('loader_a');
        $loaderA->method('find')->willReturn($this->makeImageBinary());

        $loaderB = $this->createMock(LoaderInterface::class);
        $loaderB->method('getName')->willReturn('loader_b');

        $this->filterConfig->method('get')->willReturn(['loader' => 'loader_a', 'default_image' => null]);

        $manager = $this->makeManager();
        $manager->addLoaders([$loaderA, $loaderB]);

        $result = $manager->find('thumbnail', 'photo.jpg');

        self::assertSame('image/jpeg', $result->getMimeType());
    }

    #[Test]
    public function getDefaultImageUrlReturnsFilterSpecificDefault(): void
    {
        $this->filterConfig->method('get')->willReturn([
            'default_image' => '/images/filter-default.png',
            'loader' => null,
        ]);

        $manager = $this->makeManager(globalDefault: '/images/global-default.png');

        self::assertSame('/images/filter-default.png', $manager->getDefaultImageUrl('thumbnail'));
    }

    #[Test]
    public function getDefaultImageUrlFallsBackToGlobalDefault(): void
    {
        $this->filterConfig->method('get')->willReturn([
            'default_image' => null,
            'loader' => null,
        ]);

        $manager = $this->makeManager(globalDefault: '/images/global-default.png');

        self::assertSame('/images/global-default.png', $manager->getDefaultImageUrl('thumbnail'));
    }

    #[Test]
    public function getDefaultImageUrlReturnsNullWhenNoDefaultConfigured(): void
    {
        $this->filterConfig->method('get')->willReturn([
            'default_image' => null,
            'loader' => null,
        ]);

        $manager = $this->makeManager();

        self::assertNull($manager->getDefaultImageUrl('thumbnail'));
    }

    private function makeManager(string|null $globalDefault = null): DataManager
    {
        return new DataManager($this->mimeTypes, $this->filterConfig, $this->contentGuesser, null, $globalDefault);
    }

    private function makeImageBinary(): BinaryInterface
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getMimeType')->willReturn('image/jpeg');

        return $binary;
    }
}
