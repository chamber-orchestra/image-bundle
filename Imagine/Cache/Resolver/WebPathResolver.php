<?php

declare(strict_types=1);

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
    )
    {
        $this->webRoot = \rtrim(\str_replace('//', '/', $webRootDir), '/');
        $this->cachePrefix = \ltrim(\str_replace('//', '/', $cachePrefix), '/');
        $this->cacheRoot = $this->webRoot.'/'.$this->cachePrefix;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $path, string $filter): string
    {
        return \sprintf('/%s', $this->getFileUrl($path, $filter));
    }

    /**
     * {@inheritdoc}
     */
    public function isStored(string $path, string $filter): bool
    {
        return \is_file($this->getFilePath($path, $filter));
    }

    /**
     * {@inheritdoc}
     */
    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $this->filesystem->dumpFile(
            $this->getFilePath($path, $filter),
            $binary->getContent()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $path, string $filter, string $runtimeDir): void
    {
        $this->filesystem->remove($this->getFilePath($path, $filter));

        if ('' !== $runtimeDir) {
            $this->filesystem->remove($this->getFilePath($runtimeDir, $filter));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilePath(string $path, string $filter = ''): string
    {
        return $this->webRoot.'/'.$this->getFileUrl($path, $filter);
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileUrl(string $path, string $filter = ''): string
    {
        $path = \str_replace('://', '---', $path);
        $segments = \array_filter([$this->cachePrefix, $filter, \ltrim($path, '/')]);

        return \implode('/', $segments);
    }

}
