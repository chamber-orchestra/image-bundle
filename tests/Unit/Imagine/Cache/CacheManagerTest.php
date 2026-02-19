<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\ResolverInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\SignerInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class CacheManagerTest extends TestCase
{
    private FilterConfiguration $filterConfig;
    private RouterInterface $router;
    private SignerInterface $signer;

    protected function setUp(): void
    {
        $this->filterConfig = $this->createMock(FilterConfiguration::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->signer = $this->createMock(SignerInterface::class);
    }

    #[Test]
    public function getBrowserPathReturnsResolvedUrlWhenStored(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(true);
        $resolver->method('resolve')->willReturn('/media/cache/thumbnail/photo.jpg');

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $url = $manager->getBrowserPath('photo.jpg', 'thumbnail');

        self::assertSame('/media/cache/thumbnail/photo.jpg', $url);
    }

    #[Test]
    public function getBrowserPathGeneratesUrlWhenNotStored(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);
        $this->router->method('generate')->willReturn('/_media/cache/resolve/thumbnail/photo.jpg');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $url = $manager->getBrowserPath('photo.jpg', 'thumbnail');

        self::assertSame('/_media/cache/resolve/thumbnail/photo.jpg', $url);
    }

    #[Test]
    public function getBrowserPathStripsLeadingSlashFromPath(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);
        $this->router->expects($this->once())
            ->method('generate')
            ->with($this->anything(), $this->callback(fn($p) => $p['path'] === 'photo.jpg'));

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->getBrowserPath('/photo.jpg', 'thumbnail');
    }

    #[Test]
    public function getBrowserPathWithRuntimeConfigGeneratesRuntimeUrl(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);
        $this->signer->method('sign')->willReturn('rc/abc/def12345678901234');
        $this->router->method('generate')->willReturn('/_media/cache/resolve/default/rc/abc12345');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $url = $manager->getBrowserPath('photo.jpg', 'default', ['fit' => ['width' => 800]]);

        self::assertIsString($url);
    }

    #[Test]
    public function isStoredDelegatesToResolver(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->expects($this->once())
            ->method('isStored')
            ->with('photo.jpg', 'thumbnail')
            ->willReturn(true);

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        self::assertTrue($manager->isStored('photo.jpg', 'thumbnail'));
    }

    #[Test]
    public function getResolverThrowsOutOfBoundsWhenNotRegistered(): void
    {
        $this->filterConfig->method('get')->willReturn(['resolver' => 'nonexistent']);

        $manager = $this->makeManager();

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/Could not find resolver/');

        $manager->isStored('photo.jpg', 'thumbnail');
    }

    #[Test]
    public function resolveThrowsNotFoundHttpExceptionForTraversalPaths(): void
    {
        $manager = $this->makeManager();

        $this->expectException(NotFoundHttpException::class);

        $manager->resolve('../../etc/passwd', 'thumbnail');
    }

    #[Test]
    public function resolveThrowsNotFoundForPathStartingWithDotDot(): void
    {
        $manager = $this->makeManager();

        $this->expectException(NotFoundHttpException::class);

        $manager->resolve('../secret', 'thumbnail');
    }

    #[Test]
    public function storeDelegatesToResolver(): void
    {
        $binary = $this->createMock(\ChamberOrchestra\ImageBundle\Binary\BinaryInterface::class);
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->expects($this->once())
            ->method('store')
            ->with($binary, 'photo.jpg', 'thumbnail');

        $this->filterConfig->method('get')->willReturn(['resolver' => null]);

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->store($binary, 'photo.jpg', 'thumbnail');
    }

    #[Test]
    public function removeCallsResolverForEachDefinedFilter(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->expects($this->exactly(2))
            ->method('remove');

        $this->filterConfig->method('all')->willReturn(['thumb' => [], 'large' => []]);
        $this->filterConfig->method('get')->willReturn(['resolver' => null]);
        $this->signer->method('getSignedPrefix')->willReturn('abc/def');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->remove('photo.jpg');
    }

    #[Test]
    public function removeUsesCorrectRuntimePrefixFromSigner(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);

        $this->filterConfig->method('all')->willReturn(['thumb' => []]);
        $this->filterConfig->method('get')->willReturn(['resolver' => null]);
        $this->signer->expects($this->once())
            ->method('getSignedPrefix')
            ->with('photo.jpg')
            ->willReturn('abc123/def456');

        $resolver->expects($this->once())
            ->method('remove')
            ->with('photo.jpg', 'thumb', 'rc/abc123/def456');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->remove('photo.jpg');
    }

    #[Test]
    public function getRuntimePathReturnsOutputPathFromConfig(): void
    {
        $manager = $this->makeManager();

        $runtimeConfig = ['output' => ['path' => 'custom/path.jpg']];
        $result = $manager->getRuntimePath('photo.jpg', $runtimeConfig);

        self::assertSame('custom/path.jpg', $result);
    }

    #[Test]
    public function getRuntimePathBuildsSignedPathWhenNoOutputPath(): void
    {
        $this->signer->method('sign')->willReturn('abc/def12345678901234');

        $manager = $this->makeManager();

        $result = $manager->getRuntimePath('photo.jpg', ['fit' => ['width' => 800]]);

        self::assertStringStartsWith('rc/', $result);
    }

    private function makeManager(): CacheManager
    {
        return new CacheManager($this->filterConfig, $this->router, $this->signer);
    }
}
