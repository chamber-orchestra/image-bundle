<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\ResolverInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\SignerInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
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

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
        $this->signer->method('sign')->willReturn('abcdefgh/12345678');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $url = $manager->getBrowserPath('photo.jpg', 'thumbnail', []);

        self::assertSame('/media/cache/thumbnail/photo.jpg', $url);
    }

    #[Test]
    public function getBrowserPathGeneratesUrlWhenNotStored(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
        $this->router->method('generate')->willReturn('/_media/cache/resolve/thumbnail/photo.jpg');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $url = $manager->getBrowserPath('photo.jpg', 'thumbnail', []);

        self::assertSame('/_media/cache/resolve/thumbnail/photo.jpg', $url);
    }

    #[Test]
    public function getBrowserPathStripsLeadingSlashFromPath(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
        $this->router->expects($this->once())
            ->method('generate')
            ->with($this->anything(), $this->callback(fn ($p) => 'photo.jpg' === $p['path']));

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->getBrowserPath('/photo.jpg', 'thumbnail', []);
    }

    #[Test]
    public function getBrowserPathWithRuntimeConfigGeneratesRuntimeUrl(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('isStored')->willReturn(false);

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
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

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        self::assertTrue($manager->isStored('photo.jpg', 'thumbnail'));
    }

    #[Test]
    public function getResolverThrowsOutOfBoundsWhenNotRegistered(): void
    {
        $this->filterConfig->method('get')->willReturn(['resolver' => 'nonexistent', 'secret' => 'test-secret']);

        $manager = $this->makeManager();

        $this->expectException(\OutOfBoundsException::class);
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

        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);

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
        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
        $this->signer->method('getSignedPrefix')->willReturn('Ab3xK9_z');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->remove('scores/moonlight_sonata.jpg');
    }

    #[Test]
    public function removePassesPrefixFromSignerToResolver(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);

        $this->filterConfig->method('all')->willReturn(['thumb' => []]);
        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);
        $this->signer->expects($this->once())
            ->method('getSignedPrefix')
            ->with('scores/moonlight_sonata.jpg', 'test-secret')
            ->willReturn('Ab3xK9_z');

        $resolver->expects($this->once())
            ->method('remove')
            ->with('Ab3xK9_z');

        $manager = $this->makeManager();
        $manager->addResolver($resolver, 'default');

        $manager->remove('scores/moonlight_sonata.jpg');
    }

    #[Test]
    public function getPathReturnsOutputPathFromConfig(): void
    {
        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);

        $manager = $this->makeManager();

        $runtimeConfig = ['output' => ['path' => 'custom/path.jpg']];
        $result = $manager->getPath('photo.jpg', $runtimeConfig, 'thumbnail');

        self::assertSame('custom/path.jpg', $result);
    }

    #[Test]
    public function getPathBuildsSignedPathWhenNoOutputPath(): void
    {
        $this->signer->method('sign')->willReturn('abc/def12345678901234');
        $this->filterConfig->method('get')->willReturn(['resolver' => 'default', 'secret' => 'test-secret']);

        $manager = $this->makeManager();

        $result = $manager->getPath('photo.jpg', ['fit' => ['width' => 800, 'density' => 2], 'output' => ['format' => 'webp']], 'thumbnail');

        self::assertSame('abc/def12345678901234/photo@2x.webp', $result);
    }

    private function makeManager(): CacheManager
    {
        return new CacheManager($this->filterConfig, $this->router, $this->signer);
    }
}
