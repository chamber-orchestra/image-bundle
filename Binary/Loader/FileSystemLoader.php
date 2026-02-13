<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\Locator\LocatorInterface;
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

        return new FileBinary(
            $path,
            $mime,
            $this->mimeTypes->getExtensions($mime)[0]
        );
    }
}
