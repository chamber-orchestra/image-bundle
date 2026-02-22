<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Loader;

use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;

class StreamLoader extends AbstractLoader implements LoaderInterface
{
    /**
     * The wrapper prefix to append to the path to be loaded.
     */
    protected string $wrapperPrefix;
    /**
     * A stream context resource to use.
     *
     * @var resource|null
     */
    protected $context;

    /**
     * @param resource|null $context
     */
    public function __construct(string $wrapperPrefix, mixed $context = null)
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

        \set_error_handler(static function (int $severity, string $message) use ($name): never {
            throw new NotLoadableException(\sprintf('Source image "%s" could not be loaded: %s', $name, $message));
        });

        try {
            $content = $this->context
                ? \file_get_contents($name, false, $this->context)
                : \file_get_contents($name);
        } finally {
            \restore_error_handler();
        }

        if (false === $content) {
            throw new NotLoadableException(\sprintf('Source image "%s" could not be loaded.', $name));
        }

        return $content;
    }
}
