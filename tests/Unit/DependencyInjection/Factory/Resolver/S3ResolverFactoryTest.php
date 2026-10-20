<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\DependencyInjection\Factory\Resolver;

use Aws\S3\S3Client;
use ChamberOrchestra\FileBundle\Storage\S3RedirectGuard;
use ChamberOrchestra\ImageBundle\DependencyInjection\Configuration;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Loader\FileSystemLoaderFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\S3ResolverFactory;
use ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver\WebPathResolverFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class S3ResolverFactoryTest extends TestCase
{
    private const string CLIENT_ID = 'chamber_orchestra_image.cache.resolver.scores.client';
    private const string PATH = 'chamber_orchestra_image.resolvers.scores.';

    private const array RESOLVER = [
        'type' => 's3',
        'bucket' => 'symphony-scores',
        'region' => 'eu-west-2',
        'credentials' => ['key' => 'conductor', 'secret' => 'baton'],
    ];

    private const array CLIENT_ARGS = [
        'region' => 'eu-west-2',
        'version' => 'latest',
        'credentials' => ['key' => 'conductor', 'secret' => 'baton'],
    ];

    private const array HTTP_ARGS = [
        'connect_timeout' => 5.0,
        'timeout' => 10.5,
        'allow_redirects' => false,
        'curl' => [\CURLOPT_FORBID_REUSE => true],
    ];

    #[Test]
    public function acceptsBothBlocksAndCastsIntegerSecondsToFloat(): void
    {
        $config = $this->process([
            'http' => ['connect_timeout' => 5, 'timeout' => 0.001],
            'retries' => ['mode' => 'adaptive', 'max_attempts' => 1],
        ]);

        self::assertSame(['connect_timeout' => 5.0, 'timeout' => 0.001], $config['http']);
        self::assertSame(['mode' => 'adaptive', 'max_attempts' => 1], $config['retries']);
    }

    #[Test]
    public function leavesBothBlocksOutWhenNotSet(): void
    {
        $config = $this->process([]);

        self::assertArrayNotHasKey('http', $config);
        self::assertArrayNotHasKey('retries', $config);
    }

    /**
     * @param array<string, mixed> $blocks
     */
    #[Test]
    #[DataProvider('invalidBlocks')]
    public function rejectsAnInvalidBlockNamingItsKeyPath(array $blocks, string $path): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"'.\preg_quote(self::PATH.$path, '/').'"/');

        $this->process($blocks);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidBlocks(): iterable
    {
        $http = ['connect_timeout' => 5, 'timeout' => 10];
        $retries = ['mode' => 'standard', 'max_attempts' => 2];

        yield 'http without timeout' => [['http' => ['connect_timeout' => 5]], 'http'];
        yield 'http without connect_timeout' => [['http' => ['timeout' => 10]], 'http'];
        yield 'http with an unknown key' => [['http' => $http + ['read_timeout' => 3]], 'http'];
        yield 'infinite timeout' => [['http' => ['timeout' => \INF] + $http], 'http.timeout'];
        yield 'timeout below 1 ms' => [['http' => ['timeout' => 0.0009] + $http], 'http.timeout'];
        yield 'zero connect_timeout' => [['http' => ['connect_timeout' => 0] + $http], 'http.connect_timeout'];
        yield 'negative connect_timeout' => [['http' => ['connect_timeout' => -1.0] + $http], 'http.connect_timeout'];
        yield 'non-numeric timeout' => [['http' => ['timeout' => '10s'] + $http], 'http.timeout'];
        yield 'retries without max_attempts' => [['retries' => ['mode' => 'standard']], 'retries'];
        yield 'retries without mode' => [['retries' => ['max_attempts' => 2]], 'retries'];
        yield 'retries with an unknown key' => [['retries' => $retries + ['delay' => 1]], 'retries'];
        yield 'unknown mode' => [['retries' => ['mode' => 'aggressive'] + $retries], 'retries.mode'];
        yield 'zero max_attempts' => [['retries' => ['max_attempts' => 0] + $retries], 'retries.max_attempts'];
        yield 'fractional max_attempts' => [['retries' => ['max_attempts' => 1.5] + $retries], 'retries.max_attempts'];
    }

    #[Test]
    public function withoutBlocksTheClientDefinitionIsUnchanged(): void
    {
        $definition = $this->createClientDefinition([]);

        self::assertEquals(new Definition(S3Client::class, [self::CLIENT_ARGS]), $definition);
        self::assertSame([self::CLIENT_ARGS], $definition->getArguments());
        self::assertNull($definition->getConfigurator());
    }

    #[Test]
    public function httpBlockSetsTimeoutsNoRedirectsNoReuseAndTheRedirectGuard(): void
    {
        $definition = $this->createClientDefinition(['http' => ['connect_timeout' => 5, 'timeout' => 10.5]]);

        self::assertSame([self::CLIENT_ARGS + ['http' => self::HTTP_ARGS]], $definition->getArguments());
        self::assertSame([S3RedirectGuard::class, 'register'], $definition->getConfigurator());
    }

    #[Test]
    public function retriesBlockSetsTheRetryPolicyWithoutTheRedirectGuard(): void
    {
        $definition = $this->createClientDefinition(['retries' => ['mode' => 'legacy', 'max_attempts' => 3]]);

        self::assertSame([self::CLIENT_ARGS + ['retries' => ['mode' => 'legacy', 'max_attempts' => 3]]], $definition->getArguments());
        self::assertNull($definition->getConfigurator());
    }

    #[Test]
    public function bothBlocksBuildAClientWithTheRedirectGuard(): void
    {
        $definition = $this->createClientDefinition([
            'http' => ['connect_timeout' => 5, 'timeout' => 10.5],
            'retries' => ['mode' => 'standard', 'max_attempts' => 2],
        ]);

        $args = self::CLIENT_ARGS + ['http' => self::HTTP_ARGS, 'retries' => ['mode' => 'standard', 'max_attempts' => 2]];
        self::assertSame([$args], $definition->getArguments());

        // The real SDK accepts the arguments and the configurator appends the guard to the init step.
        $client = new S3Client($args);
        $configurator = $definition->getConfigurator();
        self::assertIsCallable($configurator);
        $configurator($client);
        self::assertStringContainsString(S3RedirectGuard::NAME, (string) $client->getHandlerList());
    }

    /**
     * @param array<string, mixed> $blocks
     *
     * @return array<string, mixed>
     */
    private function process(array $blocks): array
    {
        $configuration = new Configuration(
            ['web_path' => new WebPathResolverFactory(), 's3' => new S3ResolverFactory()],
            ['filesystem' => new FileSystemLoaderFactory()],
        );
        /** @var array{resolvers: array{scores: array<string, mixed>}} $config */
        $config = (new Processor())->processConfiguration($configuration, [['resolvers' => ['scores' => self::RESOLVER + $blocks]]]);

        return $config['resolvers']['scores'];
    }

    /**
     * @param array<string, mixed> $blocks
     */
    private function createClientDefinition(array $blocks): Definition
    {
        $container = new ContainerBuilder();
        (new S3ResolverFactory())->create($container, 'scores', $this->process($blocks));

        return $container->getDefinition(self::CLIENT_ID);
    }
}
