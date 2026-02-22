<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Bundle;

use ChamberOrchestra\ImageBundle\ChamberOrchestraImageBundle;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class BundleBootIntegrationTest extends TestCase
{
    private string $projectDir;
    private ?TestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->projectDir = \sys_get_temp_dir().'/image_bundle_project_'.\uniqid();
        \mkdir($this->projectDir.'/public', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();

        $fs = new Filesystem();
        $fs->remove($this->projectDir);
    }

    #[Test]
    public function containerCompilesWithoutErrors(): void
    {
        $this->bootKernel();

        self::assertNotNull($this->kernel?->getContainer());
    }

    #[Test]
    public function filterServiceIsWired(): void
    {
        $this->bootKernel();

        $service = $this->kernel?->getContainer()->get('test.'.FilterService::class);

        self::assertInstanceOf(FilterService::class, $service);
    }

    #[Test]
    public function cacheManagerIsWired(): void
    {
        $this->bootKernel();

        $service = $this->kernel?->getContainer()->get('test.'.CacheManager::class);

        self::assertInstanceOf(CacheManager::class, $service);
    }

    #[Test]
    public function signerIsWired(): void
    {
        $this->bootKernel();

        $service = $this->kernel?->getContainer()->get('test.'.Signer::class);

        self::assertInstanceOf(Signer::class, $service);
    }

    #[Test]
    public function filterConfigurationContainsDefinedFilters(): void
    {
        $this->bootKernel();

        /** @var FilterConfiguration $filterConfig */
        $filterConfig = $this->kernel?->getContainer()->get('test.'.FilterConfiguration::class);

        self::assertInstanceOf(FilterConfiguration::class, $filterConfig);

        $config = $filterConfig->get('default');
        self::assertIsArray($config);
        self::assertArrayHasKey('resolver', $config);
    }

    private function bootKernel(): void
    {
        $this->kernel = new TestKernel($this->projectDir);
        $this->kernel->boot();

        // Set synthetic router service after boot
        $router = $this->createStub(RouterInterface::class);
        $this->kernel->getContainer()->set(RouterInterface::class, $router);
    }
}

/**
 * @internal
 */
class TestKernel extends Kernel
{
    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [
            new ChamberOrchestraImageBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $projectDir = $this->projectDir;

        $loader->load(function (ContainerBuilder $container) use ($projectDir): void {
            $container->setParameter('kernel.secret', 'test-kernel-secret-for-bundle-boot');
            $container->setParameter('kernel.project_dir', $projectDir);

            // Register services normally provided by FrameworkBundle
            $container->setDefinition('mime_types', new Definition(MimeTypes::class));
            $container->setDefinition(Filesystem::class, new Definition(Filesystem::class));
            $container->setDefinition(RequestContext::class, new Definition(RequestContext::class));

            $routerDef = new Definition(RouterInterface::class);
            $routerDef->setSynthetic(true)->setPublic(true);
            $container->setDefinition(RouterInterface::class, $routerDef);

            $container->loadFromExtension('chamber_orchestra_image', [
                'driver' => 'Imagine\Gd\Imagine',
                'cache_path' => $projectDir.'/public/media',
                'resolvers' => [
                    'default' => [
                        'type' => 'web_path',
                        'web_root' => $projectDir.'/public',
                        'bucket' => 'unused',
                    ],
                ],
                'filters' => [
                    'default' => [
                        'processors' => [],
                    ],
                ],
            ]);

            // Register a compiler pass to expose services for testing
            $container->addCompilerPass(new class implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    $services = [
                        FilterService::class,
                        CacheManager::class,
                        Signer::class,
                        FilterConfiguration::class,
                    ];

                    foreach ($services as $id) {
                        if ($container->hasDefinition($id)) {
                            $container->getDefinition($id)->setPublic(true);
                            $container->setAlias('test.'.$id, $id)->setPublic(true);
                        }
                    }
                }
            });
        });
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir.'/var/cache';
    }

    public function getLogDir(): string
    {
        return $this->projectDir.'/var/log';
    }
}
