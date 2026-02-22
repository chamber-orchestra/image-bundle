<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Data;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\BinaryMimeTypeGuesser;
use ChamberOrchestra\ImageBundle\Binary\Loader\LoaderInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Mime\MimeTypesInterface;

class DataManager
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/tiff',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    private ?string $globalDefaultImage = null;
    /**
     * @var LoaderInterface[]
     */
    private array $loaders = [];

    public function __construct(
        private readonly MimeTypesInterface $mimeTypes,
        private readonly FilterConfiguration $filterConfig,
        private readonly BinaryMimeTypeGuesser $contentGuesser,
        ?string $globalDefaultImage = null,
    ) {
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
     * @throws \InvalidArgumentException
     */
    public function getLoader(string $filter): LoaderInterface
    {
        $config = $this->filterConfig->get($filter);

        /** @var string $loaderName */
        $loaderName = $config['loader'];

        if (!isset($this->loaders[$loaderName])) {
            throw new \InvalidArgumentException(\sprintf('Could not find data loader "%s" for "%s" filter type', $loaderName, $filter));
        }

        return $this->loaders[$loaderName];
    }

    /**
     * Retrieves an image with the given filter applied.
     *
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

        if (!\in_array($binary->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new NotLoadableException(\sprintf('The source "%s" has an unsupported MIME type: %s.', $path, $binary->getMimeType()));
        }

        return $binary;
    }

    /**
     * Get default image url with the given filter applied.
     */
    public function getDefaultImageUrl(string $filter): ?string
    {
        $config = $this->filterConfig->get($filter);

        $defaultImage = null;
        if (null !== ($config['default_image'] ?? null)) {
            /** @var string $defaultImage */
            $defaultImage = $config['default_image'];
        } elseif (null !== $this->globalDefaultImage) {
            $defaultImage = $this->globalDefaultImage;
        }

        return $defaultImage;
    }
}
