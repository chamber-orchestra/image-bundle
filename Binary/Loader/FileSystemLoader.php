<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\Locator\LocatorInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\FileBinary;
use Symfony\Component\Mime\MimeTypesInterface;

class FileSystemLoader extends AbstractLoader implements LoaderInterface
{
    public function __construct(
        private readonly MimeTypesInterface $mimeTypes,
        private readonly LocatorInterface $locator,
        array $dataRoots = [])
    {
        $this->locator->setOptions(['roots' => $dataRoots]);
    }

    public function find(string $path): FileBinary
    {
        $path = $this->locator->locate($path);
        $mime = $this->mimeTypes->guessMimeType($path);

        if (null === $mime) {
            throw new NotLoadableException(\sprintf(
                'Could not determine MIME type for source image "%s".',
                $path
            ));
        }

        // Content-based detection (finfo) can misidentify valid images on some systems
        // or when the magic database is outdated. When the detected MIME is not an image
        // type but the file extension maps to a known image format, use that as a fallback.
        if (!\str_starts_with($mime, 'image/')) {
            $ext = \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));
            foreach ($this->mimeTypes->getMimeTypes($ext) as $candidate) {
                if (\str_starts_with($candidate, 'image/')) {
                    $mime = $candidate;
                    break;
                }
            }
        }

        $extensions = $this->mimeTypes->getExtensions($mime);

        return new FileBinary($path, $mime, $extensions[0] ?? null);
    }
}
