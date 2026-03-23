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
use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use ChamberOrchestra\ImageBundle\Twig\ImageRuntime;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Generates image URLs for properties annotated with #[ImageFilter].
 *
 * Produces a keyed map of avif/webp/src × 1x/2x/3x HMAC-signed URL structures,
 * mirroring the Twig image macros, suitable for API responses.
 * Multiple attributes on the same property yield multiple keys.
 *
 * Requires symfony/serializer. The service is registered conditionally
 * in ChamberOrchestraImageExtension only when the component is available.
 */
final class ImageFilterAttributeNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const DENSITIES = [1, 2, 3];
    private const FORMATS = ['avif', 'webp'];

    /** @var array<class-string, bool> Per-request class support cache to avoid repeated reflection scans */
    private array $supportsCache = [];

    public function __construct(private readonly ImageRuntime $imageRuntime)
    {
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

        foreach ((new \ReflectionClass($object))->getProperties() as $property) {
            $attrs = $property->getAttributes(ImageFilter::class);
            if (!$attrs) {
                continue;
            }

            $property->setAccessible(true);
            $value = $property->isInitialized($object) ? $property->getValue($object) : null;

            if (!$value instanceof File || !$value->isFile() || !$value->isImage()) {
                $normalized[$property->getName()] = null;
                continue;
            }

            $uri = $value->getUri();
            if (null === $uri || '' === $uri) {
                $normalized[$property->getName()] = null;
                continue;
            }

            $path = \ltrim($uri, '/');
            $sources = [];
            foreach ($attrs as $attr) {
                $instance = $attr->newInstance();
                $sources[$instance->key] = $this->generateSources($path, $instance);
            }

            $normalized[$property->getName()] = $sources;
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

        $class = $data::class;
        if (\array_key_exists($class, $this->supportsCache)) {
            return $this->supportsCache[$class];
        }

        foreach ((new \ReflectionClass($data))->getProperties() as $property) {
            if ($property->getAttributes(ImageFilter::class)) {
                return $this->supportsCache[$class] = true;
            }
        }

        return $this->supportsCache[$class] = false;
    }

    /**
     * Mirrors the Twig macro output structure:
     *   avif → {1x, 2x, 3x}
     *   webp → {1x, 2x, 3x}
     *
     * @return array<string, array<string, string>>
     */
    private function generateSources(string $path, ImageFilter $attr): array
    {
        $result = [];

        foreach (self::FORMATS as $imageFormat) {
            $output = ['output' => ['format' => $imageFormat]];

            foreach (self::DENSITIES as $density) {
                $config = [$attr->filter => ['density' => $density], ...$output];

                $result[$imageFormat][\sprintf('%dx', $density)] = match ($attr->filter) {
                    'fit' => $this->imageRuntime->fit($path, $attr->width, $attr->height, $config, $attr->preset),
                    'optimize' => $this->imageRuntime->optimize($path, $attr->width, $config, $attr->preset),
                    default => $this->imageRuntime->fill($path, $attr->width, $attr->height, $config, $attr->preset),
                };
            }
        }

        return $result;
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
