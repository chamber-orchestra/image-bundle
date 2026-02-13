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
        return \sprintf('/%s', $this->getFileUrl($path));
    }

    /**
     * {@inheritdoc}
     */
    public function isStored(string $path, string $filter): bool
    {
        return \is_file($this->getFilePath($path));
    }

    /**
     * {@inheritdoc}
     */
    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $this->filesystem->dumpFile(
            $this->getFilePath($path),
            $binary->getContent()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $path, string $runtimeDir): void
    {
        $path = $this->getFilePath($path);
        $this->filesystem->remove($path);

        $runtimePath = $this->getFilePath($runtimeDir);
        $this->filesystem->remove($runtimePath);
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilePath(string $path): string
    {
        return $this->webRoot.'/'.$this->getFileUrl($path);
    }

    /**
     * {@inheritdoc}
     */
    protected function getFileUrl(string $path): string
    {
        $path = \str_replace('://', '---', $path);

        return $this->cachePrefix.'/'.\ltrim($path, '/');
    }

    protected function getBaseUrl(): string
    {
        $port = '';
        if ('https' == $this->requestContext->getScheme() && 443 != $this->requestContext->getHttpsPort()) {
            $port = ':'.$this->requestContext->getHttpsPort();
        }

        if ('http' == $this->requestContext->getScheme() && 80 != $this->requestContext->getHttpPort()) {
            $port = ':'.$this->requestContext->getHttpPort();
        }

        $baseUrl = $this->requestContext->getBaseUrl();
        if ('.php' == \substr($this->requestContext->getBaseUrl(), -4)) {
            $baseUrl = \pathinfo($this->requestContext->getBaseurl(), PATHINFO_DIRNAME);
        }
        $baseUrl = \rtrim($baseUrl, '/\\');

        return \sprintf('%s://%s%s%s',
            $this->requestContext->getScheme(),
            $this->requestContext->getHost(),
            $port,
            $baseUrl
        );
    }
}
