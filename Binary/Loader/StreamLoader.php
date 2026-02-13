<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;

class StreamLoader extends AbstractLoader implements LoaderInterface
{
    /**
     * The wrapper prefix to append to the path to be loaded.
     *
     * @var string
     */
    protected string $wrapperPrefix;
    /**
     * A stream context resource to use.
     *
     * @var resource|null
     */
    protected $context;

    public function __construct(string $wrapperPrefix, $context = null)
    {
        $this->wrapperPrefix = $wrapperPrefix;

        if ($context && !\is_resource($context)) {
            throw new InvalidArgumentException('The given context is no valid resource.');
        }

        $this->context = empty($context) ? null : $context;
    }

    public function find(string $path): string
    {
        $name = $this->wrapperPrefix.$path;

        /*
         * This looks strange, but at least in PHP 5.3.8 it will raise an E_WARNING if the 4th parameter is null.
         * fopen() will be called only once with the correct arguments.
         *
         * The error suppression is solely to determine whether the file exists.
         * file_exists() is not used as not all wrappers support stat() to actually check for existing resources.
         */
        if (($this->context && !$resource = @\fopen($name, 'r', null, $this->context))
            || !$resource = @\fopen($name, 'r')
        ) {
            throw new NotLoadableException(\sprintf('Source image %s not found.', $name));
        }

        // Closing the opened stream to avoid locking of the resource to find.
        fclose($resource);

        try {
            $content = \file_get_contents($name, null, $this->context);
        } catch (\Exception $e) {
            throw new NotLoadableException(\sprintf('Source image %s could not be loaded.', $name), $e->getCode(), $e);
        }

        if (false === $content) {
            throw new NotLoadableException(\sprintf('Source image %s could not be loaded.', $name));
        }

        return $content;
    }
}
