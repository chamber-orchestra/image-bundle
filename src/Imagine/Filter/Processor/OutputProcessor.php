<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\Processor;

use ChamberOrchestra\ImageBundle\Enum\ImageFormat;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OutputProcessor extends AbstractProcessor
{
    private OptionsResolver $resolver;

    public function __construct(protected readonly ImagineInterface $imagine)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'path' => null,
            'format' => null,
            'quality' => null,
        ]);

        $resolver->setAllowedValues('format', function (?string $value): bool {
            if (null === $value) {
                return true;
            }

            return \in_array($value, ImageFormat::values(), true);
        });

        $resolver->setAllowedValues('quality', function (?int $value) {
            if (null === $value) {
                return true;
            }

            return $value > 0 && $value <= 100;
        });

        $this->resolver = $resolver;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $config
     */
    #[\Override]
    public function apply(ImageInterface $image, array $options = [], array &$config = []): ImageInterface
    {
        $options = $this->resolver->resolve($options);

        $config['format'] = $options['format'] ?? $config['format'] ?? null;
        $config['quality'] = $options['quality'] ?? $config['quality'] ?? null;
        $config['path'] = $options['path'] ?? $config['path'] ?? null;

        return $image;
    }
}
