<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Security;

use ChamberOrchestra\ImageBundle\Controller\ClientAction;
use ChamberOrchestra\ImageBundle\Controller\RuntimeAction;
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
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class ExposedFilterSigningIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private Signer $signer;
    private FilterConfiguration $filterConfig;
    private CacheManager $cacheManager;
    private FilterService $filterService;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_signing_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $this->signer = new Signer();

        $this->filterConfig = new FilterConfiguration([
            'aria' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'aria-signing-secret-2024',
                'async' => false,
                'exposed' => true,
                'processors' => [],
                'post_processors' => [],
            ],
            'sonata' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'sonata-signing-secret-2024',
                'async' => false,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/_media/test');

        $this->cacheManager = new CacheManager($this->filterConfig, $router, $this->signer);

        $resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $this->cacheManager->addResolver($resolver, 'default');

        $dataManager = $this->createMock(DataManager::class);
        $dataManager->method('find')->willReturn(new Binary('image-data', 'image/jpeg', 'jpeg'));

        $filterManager = $this->createMock(FilterManager::class);
        $filterManager->method('applyFilter')->willReturn(new Binary('filtered-data', 'image/jpeg', 'jpeg'));

        $this->filterService = new FilterService(
            $dataManager,
            $filterManager,
            $this->cacheManager,
            $this->filterConfig,
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function clientActionRoundTripWithRealSigning(): void
    {
        $config = [
            'fit' => ['width' => 800, 'height' => 600, 'density' => 1],
            'output' => ['quality' => 85, 'format' => 'jpeg'],
        ];

        $relativePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');
        $request = $this->buildSignedClientRequest('scores/moonlight.jpg', 'aria', 'fit', 800, 600, 85, 'jpeg', 1, $relativePath);

        $controller = new ClientAction($this->filterService, $this->filterConfig, $this->cacheManager);
        $response = $controller($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function clientActionRejectsTamperedPath(): void
    {
        $config = [
            'fit' => ['width' => 800, 'height' => 600, 'density' => 1],
            'output' => ['quality' => 85, 'format' => 'jpeg'],
        ];

        // Sign for path A
        $relativePath = $this->cacheManager->getPath('scores/moonlight.jpg', $config, 'aria');

        // But send path B with hash from path A
        $request = $this->buildSignedClientRequest('scores/different.jpg', 'aria', 'fit', 800, 600, 85, 'jpeg', 1, $relativePath);

        $controller = new ClientAction($this->filterService, $this->filterConfig, $this->cacheManager);

        $this->expectException(BadRequestHttpException::class);
        $controller($request);
    }

    #[Test]
    public function runtimeActionRoundTripWithRealSigning(): void
    {
        $path = 'scores/moonlight.jpg';
        $config = ['fit' => ['width' => 800]];
        $secret = $this->filterConfig->get('sonata')['secret'];

        $hash = $this->signer->sign($path, $secret, $config);

        $request = new Request(
            query: ['processors' => $config],
        );

        $controller = new RuntimeAction($this->filterService, $this->filterConfig, $this->signer);
        $response = $controller($request, $hash, $path, 'sonata');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function runtimeActionRejectsTamperedConfig(): void
    {
        $path = 'scores/moonlight.jpg';
        $configA = ['fit' => ['width' => 800]];
        $configB = ['fit' => ['width' => 1200]];
        $secret = $this->filterConfig->get('sonata')['secret'];

        // Sign config A
        $hash = $this->signer->sign($path, $secret, $configA);

        // Send config B with hash from config A
        $request = new Request(
            query: ['processors' => $configB],
        );

        $controller = new RuntimeAction($this->filterService, $this->filterConfig, $this->signer);

        $this->expectException(BadRequestHttpException::class);
        $controller($request, $hash, $path, 'sonata');
    }

    #[Test]
    public function runtimeActionRejectsWrongSecret(): void
    {
        $path = 'scores/moonlight.jpg';
        $config = ['fit' => ['width' => 800]];

        // Sign with a different secret
        $hash = $this->signer->sign($path, 'wrong-secret-entirely', $config);

        $request = new Request(
            query: ['processors' => $config],
        );

        $controller = new RuntimeAction($this->filterService, $this->filterConfig, $this->signer);

        $this->expectException(BadRequestHttpException::class);
        $controller($request, $hash, $path, 'sonata');
    }

    #[Test]
    public function signerDeterminismAcrossInstances(): void
    {
        $signerA = new Signer();
        $signerB = new Signer();

        $path = 'recordings/concerto.png';
        $secret = 'determinism-test-secret';
        $config = ['fill' => ['width' => 400, 'height' => 300]];

        $hashA = $signerA->sign($path, $secret, $config);
        $hashB = $signerB->sign($path, $secret, $config);

        self::assertSame($hashA, $hashB);
    }

    #[Test]
    public function signerProducesValidBase64urlFormat(): void
    {
        $hash = $this->signer->sign('scores/moonlight.jpg', 'test-secret', ['fit' => ['width' => 800]]);

        // Hash format is "pathHash/configHash" where each part is 16 chars of base64url
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16}\/[A-Za-z0-9_-]{16}$/', $hash);
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
        // Parse relativePath: "pathHash/optionsHash/name@densityx.format"
        $parts = \explode('/', $relativePath);
        $pathHash = $parts[0];
        $optionsHash = $parts[1];

        // Parse "name@densityx.format"
        $filename = $parts[2];
        \preg_match('/^(.+)@(\d+)x\.(.+)$/', $filename, $matches);
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
