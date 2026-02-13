<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManagerAwareInterface;
use LogicException;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractFilesystemResolver implements ResolverInterface, CacheManagerAwareInterface
{
    protected string $basePath = '';
    protected CacheManager $cacheManager;
    protected int $folderPermissions = 0777;
    private ?Request $request = null;

    public function __construct(protected readonly Filesystem $filesystem)
    {
    }

    public function setCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function setFolderPermissions(int $folderPermissions): void
    {
        $this->folderPermissions = $folderPermissions;
    }

    public function isStored(string $path, string $filter): bool
    {
        return \file_exists($this->getFilePath($path, $filter));
    }

    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $filePath = $this->getFilePath($path, $filter);
        $dir = \pathinfo($filePath, PATHINFO_DIRNAME);
        $this->makeFolder($dir);

        $tmpPath = $filePath.'.'.\bin2hex(\random_bytes(4)).'.tmp';
        try {
            \file_put_contents($tmpPath, $binary->getContent());
            \rename($tmpPath, $filePath);
        } catch (\Throwable $e) {
            if (\file_exists($tmpPath)) {
                \unlink($tmpPath);
            }
            throw $e;
        }
    }

    public function remove(string $path, string $filter, string $runtimeDir): void
    {
        $this->filesystem->remove($this->getFilePath($path, $filter));

        if ('' !== $runtimeDir) {
            $this->filesystem->remove($this->getFilePath($runtimeDir, $filter));
        }
    }

    protected function getRequest(): Request
    {
        if (!$this->request) {
            throw new LogicException('The request was not injected, inject it before using resolver.');
        }

        return $this->request;
    }

    public function setRequest(Request|null $request = null): ResolverInterface
    {
        $this->request = $request;
        return $this;
    }

    protected function makeFolder(string $dir): void
    {
        if (\is_dir($dir)) {
            return;
        }

        if ($dir === \dirname($dir)) {
            throw new RuntimeException(\sprintf('Could not create directory "%s": reached filesystem root without finding an existing directory.', $dir));
        }

        try {
            $this->makeFolder(\dirname($dir));
            $this->filesystem->mkdir($dir);
            $this->filesystem->chmod($dir, $this->folderPermissions);
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Could not create directory %s', $dir), 0, $e);
        }
    }

    /**
     * Return the local filepath.
     * @param string $path   The resource path to convert
     * @param string $filter The name of the imagine filter
     *
     * @throws NotFoundHttpException
     */
    abstract protected function getFilePath(string $path, string $filter): string;
}
