<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\ResolverInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class CacheManager
{
    /** @var array<string, ResolverInterface> */
    private array $resolvers = [];

    public function __construct(
        private readonly FilterConfiguration $filterConfig,
        private readonly RouterInterface $router,
        private readonly SignerInterface $signer,
    ) {
    }

    public function addResolver(ResolverInterface $resolver, string $name): void
    {
        $this->resolvers[$name] = $resolver;

        if ($resolver instanceof CacheManagerAwareInterface) {
            $resolver->setCacheManager($this);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getBrowserPath(string $path, string $filter, array $config, ?string $resolver = null): string
    {
        $path = \ltrim($path, '/');
        self::assertSafePath($path);

        $rcPath = $this->getPath($path, $config, $filter);

        return $this->getResolver($filter, $resolver)->resolveIfStored($rcPath, $filter)
            ?? $this->generateUrl($path, $filter, $config, $resolver);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getPath(string $path, array $config, string $filter): string
    {
        /** @var array{path?: string, format?: string}|null $output */
        $output = $config['output'] ?? null;

        if (isset($output['path'])) {
            return $output['path'];
        }

        $hash = $this->signer->sign($path, $this->getFilterSecret($filter), $config);
        $name = \pathinfo($path, \PATHINFO_FILENAME);
        $format = (string) ($output['format'] ?? \pathinfo($path, \PATHINFO_EXTENSION));
        $density = $this->extractDensity($config);

        return \sprintf('%s/%s@%dx.%s', $hash, $name, $density, $format);
    }

    private function getPrefix(string $path, string $filter): string
    {
        return $this->signer->getSignedPrefix($path, $this->getFilterSecret($filter));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function generateUrl(string $path, string $filter, array $config, ?string $resolver = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $path = \ltrim($path, '/');

        $params = [
            'path' => $path,
            'filter' => $filter,
        ];

        if (null !== $resolver) {
            $params['resolver'] = $resolver;
        }

        /** @var array<string, mixed> $processors */
        $processors = $config['processors'] ?? $config;
        $params['processors'] = $processors;
        $params['hash'] = $this->signer->sign($path, $this->getFilterSecret($filter), $processors);

        return $this->router->generate('_chamber_orchestra_image', $params, $referenceType);
    }

    public function isStored(string $path, string $filter, ?string $resolver = null): bool
    {
        return $this->getResolver($filter, $resolver)->isStored($path, $filter);
    }

    public function resolveIfStored(string $path, string $filter, ?string $resolver = null): ?string
    {
        return $this->getResolver($filter, $resolver)->resolveIfStored($path, $filter);
    }

    /**
     * @throws NotFoundHttpException if the path can not be resolved
     */
    public function resolve(string $path, string $filter, ?string $resolver = null): string
    {
        self::assertSafePath($path);

        return $this->getResolver($filter, $resolver)->resolve($path, $filter);
    }

    private static function assertSafePath(string $path): void
    {
        // Normalize backslashes for cross-platform safety
        $normalized = \str_replace('\\', '/', $path);

        if (\str_contains($normalized, '/../')
            || \str_starts_with($normalized, '../')
            || \str_ends_with($normalized, '/..')
            || '..' === $normalized
            || \str_starts_with($normalized, '/')
        ) {
            throw new NotFoundHttpException(\sprintf("Source image was searched with '%s' outside of the defined root path", $path));
        }
    }

    public function store(BinaryInterface $binary, string $path, string $filter, ?string $resolver = null): void
    {
        $this->getResolver($filter, $resolver)->store($binary, $path, $filter);
    }

    public function remove(string $path): void
    {
        $path = \ltrim($path, '/');

        foreach (\array_keys($this->filterConfig->all()) as $filter) {
            $prefix = $this->getPrefix($path, $filter);
            $this->getResolver($filter, null)->remove($prefix);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function extractDensity(array $config): int
    {
        /** @var array<string, mixed> $processors */
        $processors = $config['processors'] ?? $config;

        foreach ($processors as $value) {
            if (\is_array($value) && \is_numeric($value['density'] ?? null)) {
                return (int) $value['density'];
            }
        }

        return 1;
    }

    private function getFilterSecret(string $filter): string
    {
        return $this->filterConfig->get($filter)['secret'];
    }

    /**
     * @throws \OutOfBoundsException If neither a specific nor a default resolver is available
     */
    private function getResolver(string $filter, ?string $resolver): ResolverInterface
    {
        $resolverName = $resolver ?? $this->filterConfig->get($filter)['resolver'];

        if (!isset($this->resolvers[$resolverName])) {
            throw new \OutOfBoundsException(\sprintf('Could not find resolver "%s" for "%s" filter type. Available resolvers: [%s]', $resolverName, $filter, \implode(', ', \array_keys($this->resolvers))));
        }

        return $this->resolvers[$resolverName];
    }
}
