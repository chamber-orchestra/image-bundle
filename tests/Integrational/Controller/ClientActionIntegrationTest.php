<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Controller;

use ChamberOrchestra\ImageBundle\Controller\ClientAction;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Model\Binary;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class ClientActionIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private CacheManager $cacheManager;
    private FilterConfiguration $filterConfig;
    private FilterService $filterService;
    private ClientAction $controller;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_client_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $signer = new Signer();

        $this->filterConfig = new FilterConfiguration([
            'aria' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'aria-client-secret-2024',
                'async' => false,
                'exposed' => true,
                'processors' => [],
                'post_processors' => [],
            ],
            'nocturne' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'nocturne-client-secret-2024',
                'async' => false,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/_media/test');

        $this->cacheManager = new CacheManager($this->filterConfig, $router, $signer);

        $resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $this->cacheManager->addResolver($resolver, 'default');

        $dataManager = $this->createMock(DataManager::class);
        $dataManager->method('find')->willReturn(new Binary('raw-image', 'image/jpeg', 'jpeg'));

        $filterManager = $this->createMock(FilterManager::class);
        $filterManager->method('applyFilter')->willReturn(new Binary('filtered-image', 'image/jpeg', 'jpeg'));

        $this->filterService = new FilterService(
            $dataManager,
            $filterManager,
            $this->cacheManager,
            $this->filterConfig,
        );

        $this->controller = new ClientAction($this->filterService, $this->filterConfig, $this->cacheManager);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function validExposedFilterReturns302WhenCached(): void
    {
        $config = [
            'fit' => ['width' => 800, 'height' => 600, 'density' => 1],
            'output' => ['quality' => 85, 'format' => 'jpeg'],
        ];

        $relativePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');

        // Pre-store binary at the signed path
        $binary = new Binary('cached-image-data', 'image/jpeg', 'jpeg');
        $this->cacheManager->store($binary, $relativePath, 'aria');

        $request = $this->buildSignedClientRequest('scores/moonlight.jpg', 'aria', 'fit', 800, 600, 85, 'jpeg', 1, $relativePath);

        $response = ($this->controller)($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function validExposedFilterProcessesAndReturns302WhenNotCached(): void
    {
        $config = [
            'fit' => ['width' => 400, 'height' => 300, 'density' => 1],
            'output' => ['quality' => 75, 'format' => 'webp'],
        ];

        $relativePath = $this->cacheManager->getPath('recordings/concerto.png', $config, 'aria');
        $request = $this->buildSignedClientRequest('recordings/concerto.png', 'aria', 'fit', 400, 300, 75, 'webp', 1, $relativePath);

        $response = ($this->controller)($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function missingQueryParamsReturns400(): void
    {
        $request = new Request(
            query: ['filter' => 'aria', 'type' => 'fit'],
            attributes: [
                'pathHash' => 'aaaa',
                'optionsHash' => 'bbbb',
                'name' => 'test',
                'density' => 1,
                'format' => 'jpeg',
            ],
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/Missing required query parameters/');

        ($this->controller)($request);
    }

    #[Test]
    public function nonExistingFilterReturns404(): void
    {
        $request = new Request(
            query: ['path' => 'scores/moonlight.jpg', 'filter' => 'fantasy', 'type' => 'fit'],
            attributes: [
                'pathHash' => 'aaaa',
                'optionsHash' => 'bbbb',
                'name' => 'test',
                'density' => 1,
                'format' => 'jpeg',
            ],
        );

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageMatches('/non-existing filter/');

        ($this->controller)($request);
    }

    #[Test]
    public function nonExposedFilterReturns400(): void
    {
        $request = new Request(
            query: ['path' => 'scores/moonlight.jpg', 'filter' => 'nocturne', 'type' => 'fit'],
            attributes: [
                'pathHash' => 'aaaa',
                'optionsHash' => 'bbbb',
                'name' => 'test',
                'density' => 1,
                'format' => 'jpeg',
            ],
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/not exposed/');

        ($this->controller)($request);
    }

    #[Test]
    public function incorrectPathHashReturns400(): void
    {
        $config = [
            'fit' => ['width' => 800, 'height' => 600, 'density' => 1],
            'output' => ['quality' => 85, 'format' => 'jpeg'],
        ];

        $relativePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');
        $request = $this->buildSignedClientRequest('scores/moonlight.jpg', 'aria', 'fit', 800, 600, 85, 'jpeg', 1, $relativePath);

        // Tamper the pathHash
        $request->attributes->set('pathHash', 'TAMPERED_HASH_VAL');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/Signed URL/');

        ($this->controller)($request);
    }

    #[Test]
    public function incorrectOptionsHashReturns400(): void
    {
        $config = [
            'fit' => ['width' => 800, 'height' => 600, 'density' => 1],
            'output' => ['quality' => 85, 'format' => 'jpeg'],
        ];

        $relativePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');
        $request = $this->buildSignedClientRequest('scores/moonlight.jpg', 'aria', 'fit', 800, 600, 85, 'jpeg', 1, $relativePath);

        // Tamper the optionsHash
        $request->attributes->set('optionsHash', 'TAMPERED_OPT_HSH');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/Signed URL/');

        ($this->controller)($request);
    }

    private function buildSignedClientRequest(
        string $path,
        string $filter,
        string $type,
        int $width,
        int $height,
        int $quality,
        string $format,
        int $density,
        string $relativePath,
    ): Request {
        $parts = \explode('/', $relativePath);
        $pathHash = $parts[0];
        $optionsHash = $parts[1];

        \preg_match('/^(.+)@(\d+)x\.(.+)$/', $parts[2], $matches);
        $name = $matches[1];

        return new Request(
            query: [
                'path' => $path,
                'filter' => $filter,
                'type' => $type,
                'width' => $width,
                'height' => $height,
                'quality' => $quality,
            ],
            attributes: [
                'pathHash' => $pathHash,
                'optionsHash' => $optionsHash,
                'name' => $name,
                'density' => $density,
                'format' => $format,
            ],
        );
    }
}
