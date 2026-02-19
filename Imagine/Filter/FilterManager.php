<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PostProcessorInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\ProcessorInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mime\MimeTypesInterface;

class FilterManager
{
    public function __construct(
        private readonly FilterConfiguration $filterLocator,
        private readonly ServiceLocator $processorsLocator,
        private readonly ServiceLocator $postProcessorsLocator,
        private readonly ImagineInterface $imagine,
        private readonly MimeTypesInterface $mimeTypes)
    {
    }

    public function applyFilter(BinaryInterface $binary, string $filter, array $runtimeConfig = []): BinaryInterface
    {
        $config = \array_replace_recursive(
            $this->filterLocator->get($filter),
            $runtimeConfig
        );

        return $this->apply($binary, $config);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function apply(BinaryInterface $binary, array $config): BinaryInterface
    {
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

    private function applyProcessors(ImageInterface $image, array &$config): ImageInterface
    {
        foreach ($config['processors'] as $filter => $options) {
            if (!$this->processorsLocator->has($filter)) {
                throw new InvalidArgumentException(\sprintf('Could not find processor for "%s" processor type.', $filter));
            }

            $prevImage = $image;
            /** @var ProcessorInterface $processor */
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
     * @throws InvalidArgumentException
     */
    private function applyPostProcessors(BinaryInterface $binary, array $config): BinaryInterface
    {
        foreach ($config['post_processors'] as $name => $options) {
            if (!$this->postProcessorsLocator->has($name)) {
                throw new InvalidArgumentException(\sprintf('Could not find post processor "%s".', $name));
            }
            /** @var PostProcessorInterface $processor */
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

    private function save(ImageInterface $image, BinaryInterface $binary, array $config): BinaryInterface
    {
        $filteredFormat = $config['format']                  // set by OutputProcessor at runtime
            ?? $config['output']['format']                   // set via YAML filter config
            ?? $binary->getFormat()
            ?? throw new \LogicException(\sprintf(
                'No output format could be determined for binary with mime type "%s".',
                $binary->getMimeType()
            ));

        $filteredContent = $image->get($filteredFormat, $config['output']);
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
