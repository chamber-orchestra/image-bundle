<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Binary\Locator;

use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FileSystemLocator implements LocatorInterface
{
    /**
     * @var string[]
     */
    private array $roots = [];

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options = []): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['roots' => []]);

        try {
            $options = $resolver->resolve($options);
        } catch (ExceptionInterface $e) {
            throw new InvalidArgumentException(\sprintf('Invalid options provided to %s()', __METHOD__), 0, $e);
        }

        $this->roots = \array_map([$this, 'sanitizeRootPath'], (array) $options['roots']);
    }

    /**
     * @throws NotLoadableException
     */
    public function locate(string $path): string
    {
        // Reject absolute paths — only relative paths and @root: placeholders are allowed
        if ('' !== $path && ('/' === $path[0] || '\\' === $path[0])) {
            throw new NotLoadableException(\sprintf('Source image path must be relative, got "%s"', $path));
        }

        if (null !== $absolute = $this->locateUsingRootPlaceholder($path)) {
            return $this->sanitizeAbsolutePath($absolute);
        }

        if (null !== $absolute = $this->locateUsingRootPathsSearch($path)) {
            return $this->sanitizeAbsolutePath($absolute);
        }

        return $this->sanitizeAbsolutePath($path);
    }

    public function getName(): string
    {
        return 'filesystem';
    }

    protected function generateAbsolutePath(string $root, string $path): ?string
    {
        return \realpath($root.DIRECTORY_SEPARATOR.$path) ?: null;
    }

    private function locateUsingRootPathsSearch(string $path): ?string
    {
        foreach ($this->roots as $root) {
            if (null !== $absolute = $this->generateAbsolutePath($root, $path)) {
                return $absolute;
            }
        }

        return null;
    }

    private function locateUsingRootPlaceholder(string $path): ?string
    {
        if (!\str_starts_with($path, '@') || 1 !== \preg_match('{@(?<name>[^:]+):(?<path>.+)}', $path, $matches)) {
            return null;
        }

        if (isset($this->roots[$matches['name']])) {
            return $this->generateAbsolutePath($this->roots[$matches['name']], $matches['path']);
        }

        throw new NotLoadableException(\sprintf('Invalid root placeholder "%s" for path "%s"', $matches['name'], $matches['path']));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function sanitizeRootPath(string $root): string
    {
        if ($root && false !== $realRoot = \realpath($root)) {
            return $realRoot;
        }

        throw new InvalidArgumentException(\sprintf('Root image path not resolvable "%s"', $root));
    }

    /**
     * @throws NotLoadableException
     */
    private function sanitizeAbsolutePath(string $path): string
    {
        foreach ($this->roots as $root) {
            if (\str_starts_with($path, $root.\DIRECTORY_SEPARATOR) || $path === $root) {
                return $path;
            }
        }

        throw new NotLoadableException(\sprintf('Source image invalid "%s" as it is outside of the defined root path(s) "%s"', $path, \implode(':', $this->roots)));
    }
}
