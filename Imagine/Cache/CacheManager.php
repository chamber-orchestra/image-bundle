<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\ResolverInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use OutOfBoundsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class CacheManager
{
    private array $resolvers = [];
    private string $defaultResolver;
    private string $runtimePrefix = 'rc/';

    public function __construct(
        private readonly FilterConfiguration $filterConfig,
        private readonly RouterInterface $router,
        private readonly SignerInterface $signer,
        string|null $defaultResolver = null)
    {
        $this->defaultResolver = $defaultResolver ?: 'default';
    }

    public function addResolver(ResolverInterface $resolver, string $name): void
    {
        $this->resolvers[$name] = $resolver;

        if ($resolver instanceof CacheManagerAwareInterface) {
            $resolver->setCacheManager($this);
        }
    }

    public function getBrowserPath(string $path, string $filter, array $runtimeConfig = [], string|null $resolver = null): string
    {
        $path = \ltrim($path, '/');

        if ($runtimeConfig) {
            $rcPath = $this->getRuntimePath($path, $runtimeConfig);

            return $this->isStored($rcPath, $filter, $resolver)
                ? $this->resolve($rcPath, $filter, $resolver)
                : $this->generateUrl($path, $filter, $runtimeConfig, $resolver);
        }

        return $this->isStored($path, $filter, $resolver)
            ? $this->resolve($path, $filter, $resolver)
            : $this->generateUrl($path, $filter, [], $resolver);
    }

    public function getRuntimePath(string $path, array $runtimeConfig): string
    {
        return $runtimeConfig['output']['path'] ?? $this->runtimePrefix.$this->signer->sign($path, $runtimeConfig);
    }

    public function getRuntimePrefix(string $path): string
    {
        return $this->runtimePrefix.$this->signer->getSignedPrefix($path);
    }

    public function generateUrl(string $path, string $filter, array $runtimeConfig = [], string|null $resolver = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $params = [
            'path' => $path,
            'filter' => $filter,
        ];

        if (null !== $resolver) {
            $params['resolver'] = $resolver;
        }

        if (!\count($runtimeConfig)) {
            return $this->router->generate('_chamber_orchestra_image_filter', $params, $referenceType);
        }

        $params['processors'] = $runtimeConfig;
        $params['hash'] = $this->signer->sign($path, $runtimeConfig);

        return $this->router->generate('_chamber_orchestra_image_filter_runtime', $params, $referenceType);
    }

    public function isStored(string $path, string $filter, string|null $resolver = null): bool
    {
        return $this->getResolver($filter, $resolver)->isStored($path, $filter);
    }

    /**
     * @throws NotFoundHttpException if the path can not be resolved
     */
    public function resolve(string $path, string $filter, string|null $resolver = null): string
    {
        if (\str_contains($path, '/../') || \str_starts_with($path, '../')) {
            throw new NotFoundHttpException(\sprintf("Source image was searched with '%s' outside of the defined root path", $path));
        }

        return $this->getResolver($filter, $resolver)->resolve($path, $filter);
    }

    public function store(BinaryInterface $binary, string $path, string $filter, string|null $resolver = null): void
    {
        $this->getResolver($filter, $resolver)->store($binary, $path, $filter);
    }

    public function remove(string $path): void
    {
        $filters = \array_keys($this->filterConfig->all());

        $mapping = new \SplObjectStorage();
        foreach ($filters as $filter) {
            $resolver = $this->getResolver($filter, null);
            $mapping[$resolver] = $resolver;
        }

        $prefix = $this->getRuntimePrefix($path);
        foreach ($mapping as $resolver) {
            $resolver->remove($path, $prefix);
        }
    }

    /**
     *
     * @throws OutOfBoundsException If neither a specific nor a default resolver is available
     */
    private function getResolver(string $filter, string|null $resolver): ResolverInterface
    {
        if (null === $resolver) {
            $config = $this->filterConfig->get($filter);
            $resolverName = $config['resolver'] ?: $this->defaultResolver;
        } else {
            $resolverName = $resolver;
        }

        if (!isset($this->resolvers[$resolverName])) {
            throw new OutOfBoundsException(sprintf('Could not find resolver "%s" for "%s" filter type', $resolver, $filter));
        }

        return $this->resolvers[$resolverName];
    }
}
