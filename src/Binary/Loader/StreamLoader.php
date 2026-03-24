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
    /** @var list<string> */
    private readonly array $allowedSchemes;

    /**
     * @param list<string>  $allowedSchemes
     * @param resource|null $context
     */
    public function __construct(string $wrapperPrefix, mixed $context = null, array $allowedSchemes = ['file', 'data'])
    {
        $this->wrapperPrefix = $wrapperPrefix;

        if ($context && !\is_resource($context)) {
            throw new InvalidArgumentException('The given context is no valid resource.');
        }

        $this->context = empty($context) ? null : $context;
        $this->allowedSchemes = \array_map(\strtolower(...), $allowedSchemes);
    }

    #[\Override]
    public function find(string $path): string
    {
        $name = $this->wrapperPrefix.$path;

        $this->validateScheme($name);

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

    /**
     * Validates the URI scheme against the allowlist.
     *
     * Detects schemes in both standard (scheme://) and compact (scheme:) forms
     * to cover PHP stream wrappers like "data:" that don't use "://".
     * Bare paths (absolute or relative, no colon before a slash) are treated as
     * implicit "file" and checked against the allowlist.
     */
    private function validateScheme(string $uri): void
    {
        // Extract scheme: look for "word:" at the start, but not "C:\" (Windows drive)
        if (\preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $uri, $matches)) {
            $scheme = \strtolower($matches[1]);

            // Single-letter "schemes" are Windows drive letters (C:\...), treat as file
            if (1 === \strlen($scheme)) {
                $scheme = 'file';
            }
        } else {
            // No scheme prefix — bare path (relative or absolute), implicit file access
            $scheme = 'file';
        }

        if (!\in_array($scheme, $this->allowedSchemes, true)) {
            throw new NotLoadableException(\sprintf('Stream scheme "%s" is not allowed. Allowed schemes: %s.', $scheme, \implode(', ', $this->allowedSchemes)));
        }
    }
}
