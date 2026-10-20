<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\DependencyInjection\Factory\Resolver;

use Aws\S3\S3Client;
use ChamberOrchestra\FileBundle\Storage\S3RedirectGuard;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\S3Resolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\FloatNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class S3ResolverFactory extends AbstractResolverFactory implements ResolverFactoryInterface
{
    private const array SUPPORTED_RETRY_MODES = ['legacy', 'standard', 'adaptive'];

    /**
     * @param array<string, mixed> $config
     */
    public function create(ContainerBuilder $container, string $name, array $config): string
    {
        if (!\class_exists(S3Client::class)) {
            throw new \LogicException('The "aws/aws-sdk-php" package is required for S3 resolver support. Install it with "composer require aws/aws-sdk-php".');
        }

        /** @var array<string, mixed> $clientArgs */
        $clientArgs = [
            'region' => $config['region'],
            'version' => 'latest',
        ];

        if (null !== $config['endpoint']) {
            $clientArgs['endpoint'] = $config['endpoint'];
        }

        if ($config['path_style'] ?? false) {
            $clientArgs['use_path_style_endpoint'] = true;
        }

        if (isset($config['credentials'])) {
            /** @var array{key: string, secret: string} $credentials */
            $credentials = $config['credentials'];
            $clientArgs['credentials'] = [
                'key' => $credentials['key'],
                'secret' => $credentials['secret'],
            ];
        }

        // Absent blocks are not passed, so the client definition stays as before; the options mirror file-bundle 8.1.5 storages.
        if (isset($config['http'])) {
            /** @var array{connect_timeout: float, timeout: float} $http */
            $http = $config['http'];
            $clientArgs['http'] = [
                'connect_timeout' => $http['connect_timeout'],
                'timeout' => $http['timeout'],
                // never follow a redirect, so one SDK attempt is one transfer (S3RedirectGuard turns the 3xx into an error)
                'allow_redirects' => false,
                // never reuse a connection, so Guzzle never re-sends on a dead reused one; needs ext-curl
                'curl' => [\CURLOPT_FORBID_REUSE => true],
            ];
        }

        if (isset($config['retries'])) {
            /** @var array{mode: string, max_attempts: int} $retries */
            $retries = $config['retries'];
            $clientArgs['retries'] = [
                'mode' => $retries['mode'],
                'max_attempts' => $retries['max_attempts'],
            ];
        }

        $clientDefinition = new Definition(S3Client::class, [$clientArgs]);
        if (isset($config['http'])) {
            $clientDefinition->setConfigurator([S3RedirectGuard::class, 'register']);
        }
        $clientId = \sprintf('chamber_orchestra_image.cache.resolver.%s.client', $name);
        $container->setDefinition($clientId, $clientDefinition);

        $resolverDefinition = new Definition(S3Resolver::class, [
            $clientDefinition,
            $config['bucket'],
            $config['uri_prefix'],
            $config['cache_prefix'],
            $config['cache_control'],
            $config['acl'],
        ]);

        return $this->setTaggedDefinition($name, $resolverDefinition, $container);
    }

    public function getName(): string
    {
        return 's3';
    }

    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('bucket')
                    ->defaultNull()
                    ->info('S3 bucket name for storing cached images.')
                ->end()
                ->scalarNode('region')
                    ->defaultValue('us-east-1')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('endpoint')
                    ->defaultNull()
                    ->info('Custom S3 endpoint (e.g. for MinIO or DigitalOcean Spaces).')
                ->end()
                ->scalarNode('uri_prefix')
                    ->defaultNull()
                    ->info('CDN or public URL prefix for resolved image URLs.')
                ->end()
                ->scalarNode('cache_prefix')
                    ->defaultValue('media')
                    ->cannotBeEmpty()
                    ->info('S3 key prefix for cached images.')
                ->end()
                ->scalarNode('cache_control')
                    ->defaultValue('public, max-age=31536000')
                    ->info('Cache-Control header for stored objects.')
                ->end()
                ->enumNode('acl')
                    ->values([null, 'private', 'public-read', 'public-read-write', 'authenticated-read', 'aws-exec-read', 'bucket-owner-read', 'bucket-owner-full-control'])
                    ->defaultNull()
                    ->info('ACL for stored objects (e.g. "public-read"). Null to omit.')
                ->end()
                ->booleanNode('path_style')
                    ->defaultFalse()
                    ->info('Use path-style addressing (required for LocalStack/MinIO).')
                ->end()
                ->arrayNode('credentials')
                    ->children()
                        ->scalarNode('key')->isRequired()->end()
                        ->scalarNode('secret')->isRequired()->end()
                    ->end()
                    ->info('Explicit AWS credentials. Omit to use default credential chain.')
                ->end()
                ->arrayNode('http')
                    ->info('S3 client HTTP timeouts. If set, both keys are required.')
                    ->children()
                        ->append(self::positiveSeconds('connect_timeout', 'Connection phase timeout, seconds.'))
                        ->append(self::positiveSeconds('timeout', 'Whole transfer timeout including the connection, seconds.'))
                    ->end()
                ->end()
                ->arrayNode('retries')
                    ->info('S3 client retry policy. If set, both keys are required.')
                    ->children()
                        ->enumNode('mode')
                            ->values(self::SUPPORTED_RETRY_MODES)
                            ->isRequired()
                        ->end()
                        ->integerNode('max_attempts')
                            ->min(1)
                            ->isRequired()
                            ->info('Total attempts per operation, the first one included.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private static function positiveSeconds(string $name, string $info): FloatNodeDefinition
    {
        $node = new FloatNodeDefinition($name);
        $node
            ->info($info)
            ->isRequired()
            ->beforeNormalization()
                // FloatNode accepts an int but returns it unchanged; the S3 client contract wants a float.
                ->ifTrue(static fn (mixed $v): bool => \is_int($v))
                ->then(static fn (int $v): float => (float) $v)
            ->end()
            ->validate()
                // below 1 ms or infinite, Guzzle 7 silently drops the timeout and Guzzle 8 fails every request
                ->ifTrue(static fn (float $v): bool => !(\is_finite($v) && $v >= 0.001))
                // not thenInvalid(): it formats with json_encode(), which cannot encode INF
                ->then(static fn (float $v): never => throw new \InvalidArgumentException(\sprintf('The value must be a finite number of seconds >= 0.001, got %s.', \var_export($v, true))))
            ->end()
        ;

        return $node;
    }
}
