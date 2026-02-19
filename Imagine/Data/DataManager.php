<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Data;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\BinaryMimeTypeGuesser;
use ChamberOrchestra\ImageBundle\Binary\Loader\LoaderInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Model\Binary;
use InvalidArgumentException;
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
        private readonly BinaryMimeTypeGuesser $contentGuesser,
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
     * @throws NotLoadableException
     */
    public function find(string $filter, string $path): BinaryInterface
    {
        $loader = $this->getLoader($filter);

        $binary = $loader->find($path);
        if (!$binary instanceof BinaryInterface) {
            // Loader returned raw binary content (e.g. StreamLoader).
            // Use BinaryMimeTypeGuesser which writes to a temp file before calling finfo,
            // rather than passing raw content to MimeTypes::guessMimeType() as if it were a path.
            $mimeType = $this->contentGuesser->guessMimeType($binary);

            if (null === $mimeType) {
                throw new NotLoadableException(\sprintf('The MIME type of image "%s" could not be determined.', $path));
            }

            $binary = new Binary(
                $binary,
                $mimeType,
                $this->mimeTypes->getExtensions($mimeType)[0] ?? null
            );
        }

        if (!\str_starts_with($binary->getMimeType(), 'image/')) {
            throw new NotLoadableException(\sprintf(
                'The source "%s" is not an image (detected MIME type: %s).',
                $path,
                $binary->getMimeType()
            ));
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
        if (null !== $config['default_image']) {
            $defaultImage = $config['default_image'];
        } elseif (null !== $this->globalDefaultImage) {
            $defaultImage = $this->globalDefaultImage;
        }

        return $defaultImage;
    }
}
