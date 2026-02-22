<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractResizeProcessor extends AbstractProcessor
{
    private OptionsResolver $resolver;
    protected const float DEFAULT_DENSITY = 1.0;
    protected const int MAX_PIXEL_BUDGET = 25_000_000; // ~25 megapixels

    public function __construct(protected readonly ImagineInterface $imagine)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'width' => '0', // width
            'height' => '0', // height
            'background' => '#fff', // background
            'alpha' => 0,
            'filter' => ImageInterface::FILTER_LANCZOS,
            'density' => self::DEFAULT_DENSITY,
        ]);

        $resolver->setAllowedValues('density', function (string|int|float $value): bool {
            return self::DEFAULT_DENSITY <= (float) $value;
        });

        $resolver->setNormalizer('density', function (Options $options, string|int|float $value): float {
            return (float) $value;
        });

        $resolver->setDefault('filter', function (Options $options) use ($imagine) {
            if (\str_contains($imagine::class, 'Gd')) {
                return ImageInterface::FILTER_UNDEFINED;
            }

            return ImageInterface::FILTER_LANCZOS;
        });

        $resolver->setNormalizer('width', function (Options $options, string|int|float $value): float {
            return (float) $value;
        });

        $resolver->setNormalizer('height', function (Options $options, string|int|float $value): float {
            if (0 == $value && 0 == $options['width']) {
                throw new RuntimeException('Width and Height can not be 0 simultaneously');
            }

            $height = (float) $value;
            /** @var float|int|string $rawWidth */
            $rawWidth = $options['width'];
            /** @var float|int|string $rawDensity */
            $rawDensity = $options['density'];
            $width = (float) $rawWidth;
            $density = (float) $rawDensity;

            // Prevent memory exhaustion from excessively large dimensions
            if ($width > 0 && $height > 0) {
                $pixels = ($width * $density) * ($height * $density);
                if ($pixels > self::MAX_PIXEL_BUDGET) {
                    throw new RuntimeException(\sprintf('Requested image dimensions (%dx%d at %.1fx density) exceed the maximum pixel budget of %d.', (int) $width, (int) $height, $density, self::MAX_PIXEL_BUDGET));
                }
            }

            return $height;
        });

        $resolver->setNormalizer('alpha', function (Options $options, string|int|float $value): int {
            return (int) $value;
        });

        $this->resolver = $resolver;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $config
     */
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        /** @var array<string, mixed> $options */
        $options = $this->resolver->resolve($options);

        $outputSize = $this->getOutputSize($image, $options);
        $outputDensity = $this->getOutputDensity($image, $options);
        $image = $this->doApply($image, $outputSize, $outputDensity, $options);

        $scaledSize = $outputSize->scale($outputDensity);
        $imgSize = $image->getSize();
        if ($imgSize->getWidth() === $scaledSize->getWidth() && $imgSize->getHeight() === $scaledSize->getHeight()) {
            return $image;
        }

        return $this->create($image, $outputSize, $outputDensity, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    abstract protected function doApply(ImageInterface $image, Box $size, float $density, array $options = []): ImageInterface;

    /**
     * @param array<string, mixed> $options
     */
    private function create(ImageInterface $image, Box $size, float $density, array $options): ImageInterface
    {
        $size = $size->scale($density);
        $canvas = $this->createCanvas($image, $size, $options);

        $imgSize = $image->getSize();
        $canvas->paste($image, new Point(
            (int) \max(0, \round(($size->getWidth() - $imgSize->getWidth()) / 2)),
            (int) \max(0, \round(($size->getHeight() - $imgSize->getHeight()) / 2))
        ));

        return $canvas;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createCanvas(ImageInterface $image, BoxInterface $size, array $options): ImageInterface
    {
        /** @var string $background */
        $background = $options['background'];
        /** @var int $alpha */
        $alpha = $options['alpha'];

        return $this->imagine->create($size, $image->palette()->color(
            $background,
            $image->palette()->supportsAlpha() ? $alpha : null
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getOutputSize(ImageInterface $image, array $options = []): Box
    {
        /** @var float $width */
        $width = $options['width'];
        /** @var float $height */
        $height = $options['height'];

        $imgSize = $image->getSize();
        $imgRatio = $imgSize->getWidth() / $imgSize->getHeight();

        // if one parameter is 0, sets it automatically, according to image ratio
        if (.0 === $height) {
            $height = (int) \ceil(\max(1.0, $width / $imgRatio));
        }
        if (.0 === $width) {
            $width = (int) \ceil(\max(1.0, $height * $imgRatio));
        }

        return new Box((int) $width, (int) $height);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getOutputDensity(ImageInterface $image, array $options = []): float
    {
        /** @var float $density */
        $density = $options['density'];

        return $density;
    }
}
