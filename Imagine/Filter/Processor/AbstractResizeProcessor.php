<?php

declare(strict_types=1);

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
    protected OptionsResolver $resolver;
    protected const float DEFAULT_DENSITY = 1.0;

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

        $resolver->setAllowedValues('density', function ($value): bool {
            return self::DEFAULT_DENSITY <= (float) $value;
        });

        $resolver->setNormalizer('density', function (Options $options, $value): float {
            return (float) $value;
        });

        $resolver->setDefault('filter', function (Options $options) use ($imagine) {
            $class = \get_class($imagine);
            if (\preg_match('/Gd/', $class)) {
                return ImageInterface::FILTER_UNDEFINED;
            }

            return ImageInterface::FILTER_LANCZOS;
        });

        $resolver->setNormalizer('width', function (Options $options, $value) {
            return (float) $value;
        });

        $resolver->setNormalizer('height', function (Options $options, $value) {
            if (0 == $value && 0 == $options['width']) {
                throw new RuntimeException('Width and Height can not be 0 simultaneously');
            }

            return (float) $value;
        });

        $resolver->setNormalizer('alpha', function (Options $options, $value) {
            return (int) $value;
        });

        $this->resolver = $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        $options = $this->resolver->resolve($options);

        $outputSize = $this->getOutputSize($image, $options);
        $outputDensity = $this->getOutputDensity($image, $options);
        $image = $this->doApply($image, $outputSize, $outputDensity, $options);

        return $this->create($image, $outputSize, $outputDensity, $options);
    }

    abstract protected function doApply(ImageInterface $image, Box $size, float $density, array $options = []): ImageInterface;

    private function create(ImageInterface $image, Box $size, float $density, array $options): ImageInterface
    {
        $size = $size->scale($density);
        $canvas = $this->createCanvas($image, $size, $options);

        $imgSize = $image->getSize();
        $canvas->paste($image, new Point(
            \max(0, \round(($size->getWidth() - $imgSize->getWidth()) / 2)),
            \max(0, \round(($size->getHeight() - $imgSize->getHeight()) / 2))
        ));

        return $canvas;
    }

    private function createCanvas(ImageInterface $image, BoxInterface $size, array $options): ImageInterface
    {
        return $this->imagine->create($size, $image->palette()->color(
            $options['background'],
            $image->palette()->supportsAlpha() ? $options['alpha'] : null
        ));
    }

    protected function getOutputSize(ImageInterface $image, array $options = []): Box
    {
        $width = $options['width'];
        $height = $options['height'];

        $imgSize = $image->getSize();
        $imgRatio = $imgSize->getWidth() / $imgSize->getHeight();

        // if one parameter is 0, sets it automatically, according to image ratio
        if (.0 === $height) {
            $height = \ceil(\max(0.0, $width / $imgRatio));
        }
        if (.0 === $width) {
            $width = \ceil(\max(0.0, $height * $imgRatio));
        }

        return new Box($width, $height);
    }

    protected function getOutputDensity(ImageInterface $image, array $options = []): float
    {
        return $options['density'];
    }
}
