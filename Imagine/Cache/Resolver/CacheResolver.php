<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CacheResolver implements ResolverInterface
{
    protected array $options = [];

    /**
     * Constructor.
     *
     * Available options:
     * * global_prefix
     *   A prefix for all keys within the cache. This is useful to avoid colliding keys when using the same cache for different systems.
     * * prefix
     *   A "local" prefix for this wrapper. This is useful when re-using the same resolver for multiple filters.
     * * index_key
     *   The name of the index key being used to save a list of created cache keys regarding one image and filter pairing.
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ResolverInterface $resolver,
        array $options = []
    )
    {
        $this->configureOptions($optionsResolver = new OptionsResolver());
        $this->options = $optionsResolver->resolve($options);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isStored(string $path, string $filter): bool
    {
        return $this->cache->hasItem($this->generateCacheKey($path, $filter)) || $this->resolver->isStored($path, $filter);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function resolve(string $path, string $filter): string
    {
        $key = $this->generateCacheKey($path, $filter);
        if ($this->cache->hasItem($key)) {
            $item = $this->cache->getItem($key);

            return $item->get();
        }

        $resolved = $this->resolver->resolve($path, $filter);
        $this->saveToCache($key, $resolved);

        return $resolved;
    }

    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $this->resolver->store($binary, $path, $filter);
    }

    public function remove(string $path, string $filter, string $runtimeDir): void
    {
        $this->resolver->remove($path, $filter, $runtimeDir);

        $this->removePathAndFilter($path, $filter);

        if ('' !== $runtimeDir) {
            $this->removePathAndFilter($runtimeDir, $filter);
        }
    }

    /**
     * Generate a unique cache key based on the given parameters.
     * When overriding this method, ensure generateIndexKey is adjusted accordingly.
     */
    public function generateCacheKey(string $path, string $filter): string
    {
        return \implode('.', [
            $this->sanitizeCacheKeyPart($this->options['global_prefix']),
            $this->sanitizeCacheKeyPart($this->options['prefix']),
            $this->sanitizeCacheKeyPart($filter),
            $this->sanitizeCacheKeyPart($path),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function removePathAndFilter(string $path, string $filter): void
    {
        $cacheKey = $this->generateCacheKey($path, $filter);
        $indexKey = $this->generateIndexKey($cacheKey);

        $indexItem = $this->cache->getItem($indexKey);
        if (!$indexItem->isHit()) {
            return;
        }

        $index = (array) $indexItem->get();

        if (false !== $position = \array_search($cacheKey, $index, true)) {
            unset($index[$position]);
            $this->cache->deleteItem($cacheKey);
        }

        if (empty($index)) {
            $this->cache->deleteItem($indexKey);
        } else {
            $indexItem->set(\array_values($index));
            $this->cache->save($indexItem);
        }
    }

    /**
     * Generate the index key for the given cacheKey.
     * The index contains a list of cache keys related to an image and a filter.
     */
    protected function generateIndexKey(string $cacheKey): string
    {
        $cacheKeyStack = \explode('.', $cacheKey);

        return \implode('.', [
            $this->sanitizeCacheKeyPart($this->options['global_prefix']),
            $this->sanitizeCacheKeyPart($this->options['prefix']),
            $this->sanitizeCacheKeyPart($this->options['index_key']),
            $this->sanitizeCacheKeyPart($cacheKeyStack[2]), // filter
        ]);
    }

    protected function sanitizeCacheKeyPart(string $cacheKeyPart): string
    {
        return \str_replace(['.', '\\', '/'], ['_', '.', '.'], $cacheKeyPart);
    }

    /**
     * Save the given content to the cache and update the cache index.
     * @throws InvalidArgumentException
     */
    protected function saveToCache(string $cacheKey, $content): bool
    {
        // Create or update the index list containing all cache keys for a given image and filter pairing.
        // Single fetch to avoid race conditions and redundant network round-trips.
        $indexKey = $this->generateIndexKey($cacheKey);
        $indexItem = $this->cache->getItem($indexKey);
        $index = $indexItem->isHit() ? (array) $indexItem->get() : [];

        if (!\in_array($cacheKey, $index, true)) {
            $index[] = $cacheKey;
        }

        /*
         * Only save the content if the index has been updated successfully.
         * This is required to have a (hopefully) synchronous state between cache and backend.
         *
         * "Hopefully" because there are caches (like Memcache) which will remove keys by themselves.
         */
        $indexItem->set($index)->expiresAfter($this->options['lifetime']);
        if ($this->cache->save($indexItem)) {
            $contentItem = $this->cache->getItem($cacheKey);
            $contentItem->set($content)->expiresAfter($this->options['lifetime']);

            return $this->cache->save($contentItem);
        }

        return false;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'global_prefix' => 'chamber_orchestra_image.resolver_cache',
            'prefix' => $this->resolver::class,
            'index_key' => 'index',
            'lifetime' => 3600,
        ]);

        $allowedTypesList = [
            'global_prefix' => 'string',
            'prefix' => 'string',
            'index_key' => 'string',
            'lifetime' => 'int',
        ];

        foreach ($allowedTypesList as $option => $allowedTypes) {
            $resolver->setAllowedTypes($option, $allowedTypes);
        }
    }

}
