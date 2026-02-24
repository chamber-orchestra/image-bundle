<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\Locator\LocatorInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use Symfony\Component\Mime\MimeTypesInterface;

class FileSystemLoader extends AbstractLoader implements LoaderInterface
{
    /**
     * @param array<string> $dataRoots
     */
    public function __construct(
        private readonly MimeTypesInterface $mimeTypes,
        private readonly LocatorInterface $locator,
        array $dataRoots = [])
    {
        $this->locator->setOptions(['roots' => $dataRoots]);
    }

    #[\Override]
    public function find(string $path): FileBinary
    {
        $path = $this->locator->locate($path);
        $mime = $this->mimeTypes->guessMimeType($path);

        if (null === $mime) {
            throw new NotLoadableException(\sprintf('Could not determine MIME type for source image "%s".', $path));
        }

        $extensions = $this->mimeTypes->getExtensions($mime);

        return new FileBinary($path, $mime, $extensions[0] ?? null);
    }
}
