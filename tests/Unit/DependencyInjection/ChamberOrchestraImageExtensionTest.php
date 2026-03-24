<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\DependencyInjection;

use ChamberOrchestra\ImageBundle\DependencyInjection\ChamberOrchestraImageExtension;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\FileSystemLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\S3LoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\StreamLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\CustomResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\S3ResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\WebPathResolverFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ChamberOrchestraImageExtensionTest extends TestCase
{
    private ChamberOrchestraImageExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ChamberOrchestraImageExtension();
        $this->extension->addResolverFactory(new WebPathResolverFactory());
        $this->extension->addResolverFactory(new CustomResolverFactory());
        $this->extension->addResolverFactory(new S3ResolverFactory());
        $this->extension->addLoaderFactory(new StreamLoaderFactory());
        $this->extension->addLoaderFactory(new FileSystemLoaderFactory());
        $this->extension->addLoaderFactory(new S3LoaderFactory());
    }

    #[Test]
    public function envPlaceholderResolvesToExecutableBinary(): void
    {
        $envName = 'ORCHESTRA_TEST_PNGQUANT_BIN';

        $_ENV[$envName] = '/bin/echo';
        try {
            $container = $this->createMinimalContainer();
            $container->loadFromExtension('chamber_orchestra_image', [
                'binaries' => [
                    'pngquant' => '%env('.$envName.')%',
                ],
                'filters' => [
                    'score_thumbnail' => [
                        'post_processors' => [
                            'pngquant' => [],
                        ],
                    ],
                ],
            ]);

            $this->compileContainer($container);

            self::assertStringContainsString(
                $envName,
                (string) $container->getParameter('chamber_orchestra_image.pngquant.binary'),
                'The env placeholder referencing the env var should be stored as the container parameter',
            );
        } finally {
            unset($_ENV[$envName]);
        }
    }

    #[Test]
    public function envPlaceholderResolvesToNonExecutableThrows(): void
    {
        $envName = 'ORCHESTRA_TEST_MISSING_BIN';

        $_ENV[$envName] = '/nonexistent/rehearsal/hall/pngquant';
        try {
            $container = $this->createMinimalContainer();
            $container->loadFromExtension('chamber_orchestra_image', [
                'binaries' => [
                    'pngquant' => '%env('.$envName.')%',
                ],
                'filters' => [
                    'score_thumbnail' => [
                        'post_processors' => [
                            'pngquant' => [],
                        ],
                    ],
                ],
            ]);

            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessageMatches('/pngquant.*not found/i');
            $this->compileContainer($container);
        } finally {
            unset($_ENV[$envName]);
        }
    }

    #[Test]
    public function envPlaceholderSkipsValidationWhenEnvNotSet(): void
    {
        $envName = 'ORCHESTRA_TEST_UNSET_BIN';
        unset($_ENV[$envName], $_SERVER[$envName]);
        \putenv($envName);

        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'binaries' => [
                'mozjpeg' => '%env('.$envName.')%',
            ],
            'filters' => [
                'concerto_jpeg' => [
                    'post_processors' => [
                        'mozjpeg' => [],
                    ],
                ],
            ],
        ]);

        $this->compileContainer($container);

        self::assertStringContainsString(
            $envName,
            (string) $container->getParameter('chamber_orchestra_image.mozjpeg.binary'),
            'Env placeholder is preserved when env var is not available at compile time',
        );
    }

    #[Test]
    public function literalExecutablePathPassesValidation(): void
    {
        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'binaries' => [
                'pngquant' => '/bin/echo',
            ],
            'filters' => [
                'score_thumbnail' => [
                    'post_processors' => [
                        'pngquant' => [],
                    ],
                ],
            ],
        ]);

        $this->compileContainer($container);

        self::assertSame(
            '/bin/echo',
            $container->getParameter('chamber_orchestra_image.pngquant.binary'),
        );
    }

    #[Test]
    public function literalNonExecutablePathThrows(): void
    {
        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'binaries' => [
                'pngquant' => '/nonexistent/symphony/hall/pngquant',
            ],
            'filters' => [
                'score_thumbnail' => [
                    'post_processors' => [
                        'pngquant' => [],
                    ],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/pngquant.*not found/i');
        $this->compileContainer($container);
    }

    #[Test]
    public function unusedPostProcessorBinarySkipsValidation(): void
    {
        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'binaries' => [
                'pngquant' => '/nonexistent/symphony/hall/pngquant',
            ],
            'filters' => [
                'score_thumbnail' => [
                    'post_processors' => [],
                ],
            ],
        ]);

        $this->compileContainer($container);

        self::assertTrue(true, 'No exception thrown for unused post-processor binary');
    }

    #[Test]
    public function invalidSerializerFormatThrowsAtConfigTime(): void
    {
        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'serializer' => [
                'formats' => ['exe'],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Invalid serializer format.*exe/');
        $this->compileContainer($container);
    }

    #[Test]
    public function validSerializerFormatsPassValidation(): void
    {
        $container = $this->createMinimalContainer();
        $container->loadFromExtension('chamber_orchestra_image', [
            'serializer' => [
                'formats' => ['webp', 'avif', 'png'],
            ],
        ]);

        $this->compileContainer($container);

        self::assertTrue(true, 'No exception thrown for valid serializer formats');
    }

    private function createMinimalContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.project_dir', \sys_get_temp_dir());
        $container->setParameter('kernel.secret', 'test_secret_for_rehearsal');
        $container->registerExtension($this->extension);

        return $container;
    }

    private function compileContainer(ContainerBuilder $container): void
    {
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();
    }
}
