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
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\S3Resolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class S3ResolverFactory extends AbstractResolverFactory implements ResolverFactoryInterface
{
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

        $clientDefinition = new Definition(S3Client::class, [$clientArgs]);
        $clientId = \sprintf('chamber_orchestra_image.cache.resolver.%s.client', $name);
        $container->setDefinition($clientId, $clientDefinition);

        $resolverDefinition = new Definition(S3Resolver::class, [
            $clientDefinition,
            $config['bucket'],
            $config['uri_prefix'],
            $config['cache_prefix'],
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
                    ->isRequired()
                    ->cannotBeEmpty()
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
            ->end()
        ;
    }
}
