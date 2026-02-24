<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

class WebPathResolver implements ResolverInterface
{
    protected string $webRoot;
    protected string $cachePrefix;
    protected string $cacheRoot;

    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly RequestContext $requestContext,
        string $webRootDir,
        string $cachePrefix = 'media'
    ) {
        $this->webRoot = \rtrim(\str_replace('//', '/', $webRootDir), '/');
        $this->cachePrefix = \ltrim(\str_replace('//', '/', $cachePrefix), '/');
        $this->cacheRoot = $this->webRoot.'/'.$this->cachePrefix;
    }

    #[\Override]
    public function resolve(string $path, string $filter): string
    {
        $baseUrl = \rtrim($this->requestContext->getBaseUrl(), '/');

        return $baseUrl.'/'.$this->getFileUrl($path, $filter);
    }

    #[\Override]
    public function isStored(string $path, string $filter): bool
    {
        return \is_file($this->getFilePath($path, $filter));
    }

    #[\Override]
    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $this->filesystem->dumpFile(
            $this->getFilePath($path, $filter),
            $binary->getContent()
        );
    }

    #[\Override]
    public function remove(string $prefix): void
    {
        $this->filesystem->remove($this->cacheRoot.'/'.$prefix);
    }

    #[\Override]
    public function resolveIfStored(string $path, string $filter): ?string
    {
        if (!\is_file($this->getFilePath($path, $filter))) {
            return null;
        }

        $baseUrl = \rtrim($this->requestContext->getBaseUrl(), '/');

        return $baseUrl.'/'.$this->getFileUrl($path, $filter);
    }

    protected function getFilePath(string $path, string $filter = ''): string
    {
        return $this->webRoot.'/'.$this->getFileUrl($path, $filter);
    }

    private const array DANGEROUS_EXTENSIONS = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'htaccess'];

    protected function getFileUrl(string $path, string $filter = ''): string
    {
        $path = \str_replace('://', '---', $path);

        // Block executable file extensions to prevent cache poisoning
        $ext = \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));
        if (\in_array($ext, self::DANGEROUS_EXTENSIONS, true)) {
            $path = \substr($path, 0, -\strlen($ext)).'bin';
        }

        $segments = \array_filter([$this->cachePrefix, \ltrim($path, '/')]);

        return \implode('/', $segments);
    }
}
