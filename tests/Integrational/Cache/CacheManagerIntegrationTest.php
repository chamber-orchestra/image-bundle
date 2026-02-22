<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Model\Binary;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class CacheManagerIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private CacheManager $cacheManager;
    private FilterConfiguration $filterConfig;
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_cm_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $signer = new Signer();

        $this->filterConfig = new FilterConfiguration([
            'aria' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'aria-secret-key-2024',
                'async' => false,
                'exposed' => true,
                'processors' => [],
                'post_processors' => [],
            ],
            'nocturne' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'nocturne-secret-key-2024',
                'async' => false,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
            'sonata' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'sonata-secret-key-2024',
                'async' => false,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);

        $this->router = $this->createMock(RouterInterface::class);

        $this->cacheManager = new CacheManager($this->filterConfig, $this->router, $signer);

        $resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $this->cacheManager->addResolver($resolver, 'default');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function getPathProducesConsistentSignedPath(): void
    {
        $config = ['fit' => ['width' => 800, 'height' => 600], 'output' => ['format' => 'webp']];

        $path1 = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');
        $path2 = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');

        self::assertSame($path1, $path2);
    }

    #[Test]
    public function getPathDiffersForDifferentConfigs(): void
    {
        $configA = ['fit' => ['width' => 800], 'output' => ['format' => 'webp']];
        $configB = ['fit' => ['width' => 1200], 'output' => ['format' => 'webp']];

        $pathA = $this->cacheManager->getPath('scores/moonlight.jpg', $configA, 'aria');
        $pathB = $this->cacheManager->getPath('scores/moonlight.jpg', $configB, 'aria');

        self::assertNotSame($pathA, $pathB);
    }

    #[Test]
    public function getPathDiffersForDifferentSecrets(): void
    {
        $config = ['fit' => ['width' => 800], 'output' => ['format' => 'jpeg']];

        $pathAria = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');
        $pathNocturne = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'nocturne');

        self::assertNotSame($pathAria, $pathNocturne);
    }

    #[Test]
    public function getPathIncludesDensityInFilename(): void
    {
        $config = ['fit' => ['width' => 400, 'density' => 2], 'output' => ['format' => 'webp']];

        $path = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');

        self::assertStringContainsString('@2x.webp', $path);
    }

    #[Test]
    public function storeAndResolveRoundTrip(): void
    {
        $config = ['fit' => ['width' => 800], 'output' => ['format' => 'jpeg']];
        $cachePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');

        $binary = new Binary('moonlight-image-data', 'image/jpeg', 'jpeg');
        $this->cacheManager->store($binary, $cachePath, 'aria');

        self::assertTrue($this->cacheManager->isStored($cachePath, 'aria'));

        $url = $this->cacheManager->resolve($cachePath, 'aria');
        self::assertStringContainsString('media/cache', $url);
    }

    #[Test]
    public function browserPathReturnsResolvedUrlWhenStored(): void
    {
        $config = ['fit' => ['width' => 800], 'output' => ['format' => 'jpeg']];
        $cachePath = $this->cacheManager->getPath('scores/concerto.jpg', $config, 'aria');

        $binary = new Binary('concerto-data', 'image/jpeg', 'jpeg');
        $this->cacheManager->store($binary, $cachePath, 'aria');

        $url = $this->cacheManager->getBrowserPath('scores/concerto.jpg', 'aria', $config);

        self::assertStringContainsString('media/cache', $url);
    }

    #[Test]
    public function browserPathGeneratesUrlWhenNotStored(): void
    {
        $this->router->expects(self::once())
            ->method('generate')
            ->willReturn('/_media/aria/abc123/scores/concerto.jpg');

        $config = ['fit' => ['width' => 800], 'output' => ['format' => 'jpeg']];
        $url = $this->cacheManager->getBrowserPath('scores/concerto.jpg', 'aria', $config);

        self::assertSame('/_media/aria/abc123/scores/concerto.jpg', $url);
    }

    #[Test]
    public function removeDeletesAllFilterVariants(): void
    {
        $config = ['fit' => ['width' => 400], 'output' => ['format' => 'jpeg']];

        $pathAria = $this->cacheManager->getPath('scores/waltz.jpg', $config, 'aria');
        $pathNocturne = $this->cacheManager->getPath('scores/waltz.jpg', $config, 'nocturne');

        $binary = new Binary('waltz-data', 'image/jpeg', 'jpeg');
        $this->cacheManager->store($binary, $pathAria, 'aria');
        $this->cacheManager->store($binary, $pathNocturne, 'nocturne');

        self::assertTrue($this->cacheManager->isStored($pathAria, 'aria'));
        self::assertTrue($this->cacheManager->isStored($pathNocturne, 'nocturne'));

        $this->cacheManager->remove('scores/waltz.jpg');

        self::assertFalse($this->cacheManager->isStored($pathAria, 'aria'));
        self::assertFalse($this->cacheManager->isStored($pathNocturne, 'nocturne'));
    }

    #[Test]
    public function resolveRejectsTraversalPaths(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->cacheManager->resolve('../etc/passwd', 'aria');
    }
}
