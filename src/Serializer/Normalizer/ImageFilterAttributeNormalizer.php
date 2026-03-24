<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Serializer\Normalizer;

use ChamberOrchestra\FileBundle\Model\File;
use ChamberOrchestra\ImageBundle\Serializer\Metadata\ImageFilterMetadataFactory;
use ChamberOrchestra\ImageBundle\Serializer\Metadata\ImageFilterPropertyMetadata;
use ChamberOrchestra\ImageBundle\Twig\ImageRuntime;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Generates image URLs for properties annotated with #[ImageFilter].
 *
 * Produces a keyed map of avif/webp × 1x/2x/3x HMAC-signed URL structures,
 * mirroring the Twig image macros, suitable for API responses.
 * Multiple attributes on the same property yield multiple keys.
 *
 * Metadata is resolved via ImageFilterMetadataFactory (cached cross-request).
 *
 * Requires symfony/serializer. The service is registered conditionally
 * in ChamberOrchestraImageExtension only when the component is available.
 */
final class ImageFilterAttributeNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const DEFAULT_FORMATS = ['avif', 'webp'];

    /** @var list<string> */
    private readonly array $formats;

    /**
     * @param list<string> $formats Output formats to generate (default: ['avif', 'webp'])
     */
    public function __construct(
        private readonly ImageRuntime $imageRuntime,
        private readonly ImageFilterMetadataFactory $metadataFactory,
        array $formats = self::DEFAULT_FORMATS,
    ) {
        $this->formats = $formats;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        \assert(\is_object($object));

        $context[self::class] = true;

        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($object, $format, $context);

        /** @var list<string> $ignoredAttributes */
        $ignoredAttributes = $context[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? [];
        /** @var array<int|string, mixed>|null $allowedAttributes */
        $allowedAttributes = $context[AbstractNormalizer::ATTRIBUTES] ?? null;

        foreach ($this->metadataFactory->getMetadata($object) as $propertyName => $filters) {
            if (\in_array($propertyName, $ignoredAttributes, true)) {
                continue;
            }

            if (null !== $allowedAttributes && !$this->isAttributeAllowed($propertyName, $allowedAttributes)) {
                continue;
            }

            $value = (new \ReflectionProperty($object, $propertyName))->isInitialized($object)
                ? (new \ReflectionProperty($object, $propertyName))->getValue($object)
                : null;

            if (!$value instanceof File || !$value->isFile() || !$value->isImage()) {
                $normalized[$propertyName] = null;
                continue;
            }

            $uri = $value->getUri();
            if (null === $uri || '' === $uri) {
                $normalized[$propertyName] = null;
                continue;
            }

            $path = \ltrim($uri, '/');
            $sources = [];
            foreach ($filters as $meta) {
                $sources[$meta->key] = $this->generateSources($path, $meta);
            }

            // Apply nested ATTRIBUTES filtering (e.g. ['coverArt' => ['default' => ['avif' => ['1x']]]])
            if (null !== $allowedAttributes && \is_array($allowedAttributes[$propertyName] ?? null)) {
                $sources = $this->pruneNested($sources, $allowedAttributes[$propertyName]);
            }

            $normalized[$propertyName] = $sources;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::class]) || !\is_object($data)) {
            return false;
        }

        return $this->metadataFactory->hasMetadata($data);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function generateSources(string $path, ImageFilterPropertyMetadata $meta): array
    {
        $result = [];

        foreach ($this->formats as $imageFormat) {
            $output = ['output' => ['format' => $imageFormat]];

            foreach ($meta->densities as $density) {
                $config = [$meta->filter => ['density' => $density], ...$output];

                $result[$imageFormat][\sprintf('%dx', $density)] = match ($meta->filter) {
                    'fit' => $this->imageRuntime->fit($path, $meta->width, $meta->height, $config, $meta->preset),
                    'optimize' => $this->imageRuntime->optimize($path, 0 === $meta->width ? 1200 : $meta->width, $config, $meta->preset),
                    default => $this->imageRuntime->fill($path, $meta->width, $meta->height, $config, $meta->preset),
                };
            }
        }

        return $result;
    }

    /**
     * Recursively prunes a nested array to only include keys present in the filter tree.
     * An empty filter array means "include everything at this level".
     *
     * @param array<string, mixed>     $data
     * @param array<int|string, mixed> $filter
     *
     * @return array<string, mixed>
     */
    private function pruneNested(array $data, array $filter): array
    {
        if ([] === $filter) {
            return $data;
        }

        $result = [];
        foreach ($filter as $key => $subFilter) {
            // Flat list entry: [0 => 'default']
            if (\is_int($key) && \is_string($subFilter)) {
                if (\array_key_exists($subFilter, $data)) {
                    $result[$subFilter] = $data[$subFilter];
                }
                continue;
            }

            // Associative entry: ['default' => ['avif' => ['1x']]]
            if (\is_string($key) && \array_key_exists($key, $data)) {
                $result[$key] = \is_array($subFilter) && \is_array($data[$key])
                    ? $this->pruneNested($data[$key], $subFilter)
                    : $data[$key];
            }
        }

        return $result;
    }

    /**
     * Checks whether a property is allowed by the ATTRIBUTES context.
     * Handles both flat lists (['a', 'b']) and associative trees (['a' => [], 'b' => ['c']]).
     *
     * @param array<int|string, mixed> $allowedAttributes
     */
    private function isAttributeAllowed(string $name, array $allowedAttributes): bool
    {
        // Flat list: ['coverArt', 'title']
        if (\in_array($name, $allowedAttributes, true)) {
            return true;
        }

        // Associative: ['coverArt' => [], 'title' => [...]]
        return \array_key_exists($name, $allowedAttributes);
    }

    /**
     * @return array<string, bool>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
