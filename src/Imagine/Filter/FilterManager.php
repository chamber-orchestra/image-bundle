<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PostProcessorInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\ProcessorInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mime\MimeTypesInterface;

class FilterManager
{
    /**
     * @param ServiceLocator<ProcessorInterface>     $processorsLocator
     * @param ServiceLocator<PostProcessorInterface> $postProcessorsLocator
     */
    public function __construct(
        private readonly FilterConfiguration $filterLocator,
        private readonly ServiceLocator $processorsLocator,
        private readonly ServiceLocator $postProcessorsLocator,
        private readonly ImagineInterface $imagine,
        private readonly MimeTypesInterface $mimeTypes)
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function applyFilter(BinaryInterface $binary, string $filter, array $config = []): BinaryInterface
    {
        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive(
            $this->filterLocator->get($filter),
            $config
        );

        return $this->apply($binary, $config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException
     */
    private function apply(BinaryInterface $binary, array $config): BinaryInterface
    {
        /** @var array<string, mixed> $config */
        $config = \array_replace_recursive([
            'output' => [],
            'processors' => [],
            'post_processors' => [],
        ], $config);

        $image = $this->open($binary);
        $image = $this->applyProcessors($image, $config);
        $binary = $this->save($image, $binary, $config);

        return $this->applyPostProcessors($binary, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function applyProcessors(ImageInterface $image, array &$config): ImageInterface
    {
        /** @var array<string, array<string, mixed>> $processors */
        $processors = $config['processors'];

        foreach ($processors as $filter => $options) {
            if (!$this->processorsLocator->has($filter)) {
                throw new \InvalidArgumentException(\sprintf('Could not find processor for "%s" processor type.', $filter));
            }

            $prevImage = $image;
            $processor = $this->processorsLocator->get($filter);
            $image = $processor->apply($image, $options, $config);

            // If the filter returns a different image object destruct the old one because imagick keeps consuming memory if we don't
            if ($prevImage !== $image && \method_exists($prevImage, '__destruct')) {
                $prevImage->__destruct();
            }
        }

        return $image;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException
     */
    private function applyPostProcessors(BinaryInterface $binary, array $config): BinaryInterface
    {
        /** @var array<string, array<string, mixed>> $postProcessors */
        $postProcessors = $config['post_processors'];

        foreach ($postProcessors as $name => $options) {
            if (!$this->postProcessorsLocator->has($name)) {
                throw new \InvalidArgumentException(\sprintf('Could not find post processor "%s".', $name));
            }
            $processor = $this->postProcessorsLocator->get($name);
            $binary = $processor->process($binary, $options);
        }

        return $binary;
    }

    private function open(BinaryInterface $binary): ImageInterface
    {
        if ($binary instanceof FileBinaryInterface) {
            return $this->imagine->open($binary->getPath());
        }

        return $this->imagine->load($binary->getContent());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function save(ImageInterface $image, BinaryInterface $binary, array $config): BinaryInterface
    {
        /** @var array<string, mixed> $output */
        $output = $config['output'];

        /** @var string $filteredFormat */
        $filteredFormat = $config['format']                  // set by OutputProcessor at runtime
            ?? $output['format']                             // set via YAML filter config
            ?? $binary->getFormat()
            ?? throw new \LogicException(\sprintf('No output format could be determined for binary with mime type "%s".', $binary->getMimeType()));

        // Strip keys that are bundle-internal and not Imagine driver options
        $imagineOptions = \array_diff_key($output, \array_flip(['format', 'optimize', 'flatten', 'animated']));
        $filteredContent = $image->get($filteredFormat, $imagineOptions);
        $filteredMimeType = $filteredFormat === $binary->getFormat()
            ? $binary->getMimeType()
            : ($this->mimeTypes->getMimeTypes($filteredFormat)[0] ?? $binary->getMimeType());

        // We are done with the image object so we can destruct the this because imagick keeps consuming memory if we don't
        if (\method_exists($image, '__destruct')) {
            $image->__destruct();
        }

        return new Binary($filteredContent, $filteredMimeType, $filteredFormat);
    }
}
