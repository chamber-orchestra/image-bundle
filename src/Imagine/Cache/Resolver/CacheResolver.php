<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CacheResolver implements ResolverInterface
{
    /** @var array<string, mixed> */
    protected array $options = [];

    /**
     * @param array{global_prefix?: string, prefix?: string, index_key?: string, lifetime?: int} $options
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ResolverInterface $resolver,
        array $options = []
    ) {
        $this->configureOptions($optionsResolver = new OptionsResolver());
        /** @var array<string, mixed> $resolved */
        $resolved = $optionsResolver->resolve($options);
        $this->options = $resolved;
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
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            /** @var string $resolved */
            $resolved = $item->get();

            return $resolved;
        }

        $resolved = $this->resolver->resolve($path, $filter);
        $this->saveToCache($key, $this->extractPrefix($path), $resolved);

        return $resolved;
    }

    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $this->resolver->store($binary, $path, $filter);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function remove(string $prefix): void
    {
        $this->resolver->remove($prefix);

        $indexKey = $this->generateIndexKey($prefix);
        $indexItem = $this->cache->getItem($indexKey);

        if ($indexItem->isHit()) {
            foreach ((array) $indexItem->get() as $cacheKey) {
                $this->cache->deleteItem((string) $cacheKey);
            }
            $this->cache->deleteItem($indexKey);
        }
    }

    /**
     * Generate a unique cache key based on the given parameters.
     */
    public function generateCacheKey(string $path, string $filter): string
    {
        return \implode('.', [
            $this->sanitizeCacheKeyPart($this->getStringOption('global_prefix')),
            $this->sanitizeCacheKeyPart($this->getStringOption('prefix')),
            $this->sanitizeCacheKeyPart($filter),
            $this->sanitizeCacheKeyPart($path),
        ]);
    }

    /**
     * Generate the index key for the given path prefix.
     * The index contains a list of all cache keys stored under this prefix.
     */
    protected function generateIndexKey(string $prefix): string
    {
        return \implode('.', [
            $this->sanitizeCacheKeyPart($this->getStringOption('global_prefix')),
            $this->sanitizeCacheKeyPart($this->getStringOption('prefix')),
            $this->sanitizeCacheKeyPart($this->getStringOption('index_key')),
            $this->sanitizeCacheKeyPart($prefix),
        ]);
    }

    protected function sanitizeCacheKeyPart(string $cacheKeyPart): string
    {
        return \str_replace(
            ['.', '\\', '/', '{', '}', '(', ')', '@', ':'],
            ['_', '_',  '_', '_', '_', '_', '_', '_', '_'],
            $cacheKeyPart
        );
    }

    /**
     * Save the given content to the cache and update the prefix index.
     *
     * @throws InvalidArgumentException
     */
    protected function saveToCache(string $cacheKey, string $prefix, mixed $content): bool
    {
        $indexKey = $this->generateIndexKey($prefix);
        $indexItem = $this->cache->getItem($indexKey);
        $index = $indexItem->isHit() ? (array) $indexItem->get() : [];

        if (!\in_array($cacheKey, $index, true)) {
            $index[] = $cacheKey;
        }

        $lifetime = $this->getIntOption('lifetime');
        $indexItem->set($index)->expiresAfter($lifetime);
        if ($this->cache->save($indexItem)) {
            $contentItem = $this->cache->getItem($cacheKey);
            $contentItem->set($content)->expiresAfter($lifetime);

            return $this->cache->save($contentItem);
        }

        return false;
    }

    /**
     * Extracts the path prefix (first segment before '/') from a cache path.
     */
    private function extractPrefix(string $path): string
    {
        $pos = \strpos($path, '/');

        return false !== $pos ? \substr($path, 0, $pos) : $path;
    }

    private function getStringOption(string $key): string
    {
        $value = $this->options[$key];
        \assert(\is_string($value));

        return $value;
    }

    private function getIntOption(string $key): int
    {
        $value = $this->options[$key];
        \assert(\is_int($value));

        return $value;
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
