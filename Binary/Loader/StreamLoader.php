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

        try {
            $content = $this->context
                ? @\file_get_contents($name, false, $this->context)
                : @\file_get_contents($name);
        } catch (\Exception $e) {
            throw new NotLoadableException(\sprintf('Source image "%s" could not be loaded.', $name), $e->getCode(), $e);
        }

        if (false === $content) {
            throw new NotLoadableException(\sprintf('Source image "%s" not found or could not be loaded.', $name));
        }

        return $content;
    }
}
