<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Serializer\Metadata;

use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Builds and caches per-class metadata for #[ImageFilter] properties.
 *
 * Three layers:
 *  1. In-memory array — avoids repeated lookups within the same request
 *  2. PSR-6 cache pool — cross-request reuse (prod: long TTL, dev: fingerprinted keys)
 *  3. Reflection — cold-start fallback, runs once per class per cache lifetime
 */
final class ImageFilterMetadataFactory
{
    /** @var array<class-string, array<string, list<ImageFilterPropertyMetadata>>> */
    private array $local = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * Returns a map of property name → list of filter metadata for the given class.
     * Empty array means the class has no #[ImageFilter] properties.
     *
     * @param class-string|object $class
     *
     * @return array<string, list<ImageFilterPropertyMetadata>>
     */
    public function getMetadata(string|object $class): array
    {
        $className = \is_object($class) ? $class::class : $class;

        if (isset($this->local[$className])) {
            return $this->local[$className];
        }

        if (null !== $this->cache) {
            $cacheKey = $this->buildCacheKey($className);
            $item = $this->cache->getItem($cacheKey);

            if ($item->isHit()) {
                /** @var array<string, list<ImageFilterPropertyMetadata>> $cached */
                $cached = $item->get();

                return $this->local[$className] = $cached;
            }

            $metadata = $this->reflect($className);
            $item->set($metadata);
            $item->expiresAfter($this->debug ? 60 : 86400);
            $this->cache->save($item);

            return $this->local[$className] = $metadata;
        }

        return $this->local[$className] = $this->reflect($className);
    }

    /**
     * Whether the class has at least one #[ImageFilter] property.
     *
     * @param class-string|object $class
     */
    public function hasMetadata(string|object $class): bool
    {
        return [] !== $this->getMetadata($class);
    }

    /**
     * @param class-string $className
     *
     * @return array<string, list<ImageFilterPropertyMetadata>>
     */
    private function reflect(string $className): array
    {
        $result = [];

        foreach ((new \ReflectionClass($className))->getProperties() as $property) {
            $attrs = $property->getAttributes(ImageFilter::class);
            if (!$attrs) {
                continue;
            }

            $filters = [];
            foreach ($attrs as $attr) {
                $instance = $attr->newInstance();

                foreach ($instance->densities as $density) {
                    if ($density < 1 || $density > 4) {
                        throw new \InvalidArgumentException(\sprintf('Density %d on property %s::%s is out of range (allowed: 1–4).', $density, $className, $property->getName()));
                    }
                }

                $filters[] = new ImageFilterPropertyMetadata(
                    key: $instance->key,
                    filter: $instance->filter,
                    width: $instance->width,
                    height: $instance->height,
                    densities: $instance->densities,
                    preset: $instance->preset,
                );
            }

            $result[$property->getName()] = $filters;
        }

        return $result;
    }

    /**
     * @param class-string $className
     */
    private function buildCacheKey(string $className): string
    {
        $base = 'image_filter_meta_'.\str_replace('\\', '_', $className);

        if (!$this->debug) {
            return $base;
        }

        return $base.'_'.$this->fingerprint($className);
    }

    /**
     * Dev-mode fingerprint: hash of declaring file mtimes for the class,
     * its parents, and all used traits recursively (traits of traits).
     *
     * @param class-string $className
     */
    private function fingerprint(string $className): string
    {
        $files = [];
        $ref = new \ReflectionClass($className);

        do {
            $file = $ref->getFileName();
            if (false !== $file) {
                $files[$file] = true;
            }

            $this->collectTraitFiles($ref, $files);
        } while ($ref = $ref->getParentClass());

        $mtimes = '';
        foreach ($files as $file => $_) {
            $mtimes .= $file.':'.\filemtime($file).';';
        }

        return \substr(\md5($mtimes), 0, 8);
    }

    /**
     * Recursively collects file paths from traits (including traits used by traits).
     *
     * @param array<string, true>      $files
     * @param \ReflectionClass<object> $ref
     */
    private function collectTraitFiles(\ReflectionClass $ref, array &$files): void
    {
        foreach ($ref->getTraits() as $trait) {
            $traitFile = $trait->getFileName();
            if (false !== $traitFile && !isset($files[$traitFile])) {
                $files[$traitFile] = true;
                $this->collectTraitFiles($trait, $files);
            }
        }
    }
}
