<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\FileBundle\Storage\StorageInterface;
use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Mime\MimeTypesInterface;

class S3Loader extends AbstractLoader implements LoaderInterface
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly MimeTypesInterface $mimeTypes,
    ) {
    }

    public function find(string $path): BinaryInterface
    {
        $tempFile = \sys_get_temp_dir().'/'.\uniqid('co_image_s3_', true);

        try {
            $this->storage->download($path, $tempFile);
        } catch (\Throwable $e) {
            throw new NotLoadableException(\sprintf('Source image "%s" could not be loaded from S3 storage: %s', $path, $e->getMessage()), 0, $e);
        }

        try {
            $content = \file_get_contents($tempFile);

            if (false === $content) {
                throw new NotLoadableException(\sprintf('Failed to read downloaded S3 file for path "%s".', $path));
            }

            $mime = $this->mimeTypes->guessMimeType($tempFile);

            if (null === $mime) {
                throw new NotLoadableException(\sprintf('Could not determine MIME type for source image "%s".', $path));
            }

            $extensions = $this->mimeTypes->getExtensions($mime);

            return new Binary($content, $mime, $extensions[0] ?? null);
        } finally {
            if (\file_exists($tempFile)) {
                \unlink($tempFile);
            }
        }
    }
}
