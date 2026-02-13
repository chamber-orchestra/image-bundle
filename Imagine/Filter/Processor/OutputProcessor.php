<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutputProcessor extends AbstractProcessor
{
    private const array FORMATS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'tiff', 'bmp', 'avif'];
    private OptionsResolver $resolver;

    public function __construct(protected readonly ImagineInterface $imagine)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'path' => null,
            'format' => null,
            'quality' => null,
        ]);

        $resolver->setAllowedValues('format', function (string|null $value) use ($imagine): bool {
            if (null === $value) {
                return true;
            }

            if (!\str_contains($imagine::class, 'Imagick')) {
                return false;
            }

            return \in_array($value, self::FORMATS, true);
        });

        $resolver->setAllowedValues('quality', function (int|null $value) use ($imagine) {
            if (null === $value) {
                return true;
            }

            return $value > 0 && $value <= 100;
        });

        $this->resolver = $resolver;
    }

    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        $options = $this->resolver->resolve($options);

        $config['format'] = $options['format'] ?? $config['format'] ?? null;
        $config['quality'] = $options['quality'] ?? $config['quality'] ?? null;
        $config['path'] = $options['path'] ?? $config['path'] ?? null;

        return $image;
    }
}
