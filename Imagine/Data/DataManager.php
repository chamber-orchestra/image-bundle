<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Data;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\Loader\LoaderInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Model\Binary;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Mime\MimeTypesInterface;

class DataManager
{
    private string|null $defaultLoader = null;
    private string|null $globalDefaultImage = null;
    /**
     * @var LoaderInterface[]
     */
    protected array $loaders = [];

    public function __construct(
        private readonly MimeTypesInterface $mimeTypes,
        private readonly FilterConfiguration $filterConfig,
        ?string $defaultLoader = null,
        ?string $globalDefaultImage = null
    )
    {
        $this->defaultLoader = $defaultLoader ?: 'default';
        $this->globalDefaultImage = $globalDefaultImage;
    }

    /**
     * Adds a loader to retrieve images for the given filter.
     */
    public function addLoader(LoaderInterface $loader, string $name): void
    {
        $this->loaders[$name] = $loader;
    }

    /**
     * @param LoaderInterface[]|iterable $loaders
     */
    public function addLoaders(iterable $loaders): void
    {
        foreach ($loaders as $loader) {
            $this->loaders[$loader->getName()] = $loader;
        }
    }

    /**
     * Returns a loader previously attached to the given filter.
     *
     * @throws InvalidArgumentException
     */
    public function getLoader(string $filter): LoaderInterface
    {
        $config = $this->filterConfig->get($filter);

        $loaderName = $config['loader'] ?: $this->defaultLoader;

        if (!isset($this->loaders[$loaderName])) {
            throw new InvalidArgumentException(\sprintf('Could not find data loader "%s" for "%s" filter type', $loaderName, $filter));
        }

        return $this->loaders[$loaderName];
    }

    /**
     * Retrieves an image with the given filter applied.
     * @throws LogicException
     */
    public function find(string $filter, string $path): BinaryInterface
    {
        $loader = $this->getLoader($filter);

        $binary = $loader->find($path);
        if (!$binary instanceof BinaryInterface) {
            $mimeType = $this->mimeTypes->guessMimeType($binary);

            $binary = new Binary(
                $binary,
                $mimeType,
                $this->mimeTypes->getExtensions($mimeType)[0] ?? null
            );
        }

        if (null === $binary->getMimeType()) {
            throw new LogicException(\sprintf('The mime type of image %s was not guessed.', $path));
        }

        if (!\str_starts_with($binary->getMimeType(), 'image/')) {
            throw new LogicException(\sprintf('The mime type of image %s must be image/xxx got %s.', $path, $binary->getMimeType()));
        }

        return $binary;
    }

    /**
     * Get default image url with the given filter applied.
     */
    public function getDefaultImageUrl(string $filter): string|null
    {
        $config = $this->filterConfig->get($filter);

        $defaultImage = null;
        if ($config['default_image']) {
            $defaultImage = $config['default_image'];
        } elseif ($this->globalDefaultImage) {
            $defaultImage = $this->globalDefaultImage;
        }

        return $defaultImage;
    }
}
