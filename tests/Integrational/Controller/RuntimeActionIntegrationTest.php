<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Controller;

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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class RuntimeActionIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private Signer $signer;
    private FilterConfiguration $filterConfig;
    private FilterService $filterService;
    private RuntimeAction $controller;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_runtime_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $this->signer = new Signer();

        $this->filterConfig = new FilterConfiguration([
            'sonata' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'sonata-runtime-secret-2024',
                'async' => false,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/_media/test');

        $cacheManager = new CacheManager($this->filterConfig, $router, $this->signer);

        $resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $cacheManager->addResolver($resolver, 'default');

        $dataManager = $this->createMock(DataManager::class);
        $dataManager->method('find')->willReturn(new Binary('raw-image', 'image/jpeg', 'jpeg'));

        $filterManager = $this->createMock(FilterManager::class);
        $filterManager->method('applyFilter')->willReturn(new Binary('filtered-image', 'image/jpeg', 'jpeg'));

        $this->filterService = new FilterService(
            $dataManager,
            $filterManager,
            $cacheManager,
            $this->filterConfig,
        );

        $this->controller = new RuntimeAction($this->filterService, $this->filterConfig, $this->signer);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function validSignedRuntimeUrlReturns301(): void
    {
        $path = 'scores/moonlight.jpg';
        $config = ['fit' => ['width' => 800, 'height' => 600]];
        $secret = $this->filterConfig->get('sonata')['secret'];

        $hash = $this->signer->sign($path, $secret, $config);

        $request = new Request(query: ['processors' => $config]);
        $response = ($this->controller)($request, $hash, $path, 'sonata');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function invalidSignatureReturns400(): void
    {
        $path = 'scores/moonlight.jpg';
        $config = ['fit' => ['width' => 800]];

        $request = new Request(query: ['processors' => $config]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/sign check/');

        ($this->controller)($request, 'fabricated_hash__/fabricated_hash__', $path, 'sonata');
    }

    #[Test]
    public function nonExistingFilterReturns404(): void
    {
        $request = new Request(query: ['processors' => ['fit' => ['width' => 800]]]);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageMatches('/non-existing filter/');

        ($this->controller)($request, 'abc/def', 'scores/moonlight.jpg', 'requiem');
    }

    #[Test]
    public function missingProcessorsReturns400(): void
    {
        $request = new Request(query: []);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/processors must be provided/');

        ($this->controller)($request, 'abc/def', 'scores/moonlight.jpg', 'sonata');
    }

    #[Test]
    public function deeplyNestedProcessorsReturns400(): void
    {
        $config = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'deep']]]]]];

        $request = new Request(query: ['processors' => $config]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/too deeply nested/');

        ($this->controller)($request, 'abc/def', 'scores/moonlight.jpg', 'sonata');
    }

    #[Test]
    public function differentConfigProducesDifferentHash(): void
    {
        $path = 'scores/moonlight.jpg';
        $secret = $this->filterConfig->get('sonata')['secret'];
        $configA = ['fit' => ['width' => 800]];
        $configB = ['fit' => ['width' => 1200]];

        // Sign with config A
        $hash = $this->signer->sign($path, $secret, $configA);

        // Send with config B using hash from config A
        $request = new Request(query: ['processors' => $configB]);

        $this->expectException(BadRequestHttpException::class);

        ($this->controller)($request, $hash, $path, 'sonata');
    }

    #[Test]
    public function tamperedPathFailsSignatureCheck(): void
    {
        $secret = $this->filterConfig->get('sonata')['secret'];
        $config = ['fit' => ['width' => 800]];

        // Sign for path A
        $hash = $this->signer->sign('scores/moonlight.jpg', $secret, $config);

        // Send path B with hash from path A
        $request = new Request(query: ['processors' => $config]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('/sign check/');

        ($this->controller)($request, $hash, 'scores/different.jpg', 'sonata');
    }
}
