<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Service;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

readonly class FilterService
{
    public function __construct(
        private DataManager $dataManager,
        private FilterManager $filterManager,
        private CacheManager $cacheManager,
        private LoggerInterface $logger = new NullLogger())
    {
    }

    public function getUrlOfFilteredImage(string $path, string $filter, ?string $resolver = null): string
    {
        if ($this->cacheManager->isStored($path, $filter, $resolver)) {
            return $this->cacheManager->resolve($path, $filter, $resolver);
        }

        $this->cacheManager->store($this->createFilteredBinary($path, $filter), $path, $filter, $resolver);

        return $this->cacheManager->resolve($path, $filter, $resolver);
    }

    public function getRuntimeProcessedImageUrl(string $path, string $filter, array $runtimeProcessors = [], ?string $resolver = null): string
    {
        $runtimePath = $this->cacheManager->getRuntimePath($path, $runtimeProcessors);
        if ($this->cacheManager->isStored($runtimePath, $filter, $resolver)) {
            return $this->cacheManager->resolve($runtimePath, $filter, $resolver);
        }

        $this->cacheManager->store($this->createFilteredBinary($path, $filter, $runtimeProcessors), $runtimePath, $filter, $resolver);

        return $this->cacheManager->resolve($runtimePath, $filter, $resolver);
    }

    /**
     * @throws NonExistingFilterException
     */
    private function createFilteredBinary(string $path, string $filter, array $runtimeProcessors = []): BinaryInterface
    {
        $binary = $this->dataManager->find($filter, $path);

        try {
            return $this->filterManager->applyFilter($binary, $filter, [
                'processors' => $runtimeProcessors,
            ]);
        } catch (NonExistingFilterException $e) {
            $this->logger->debug(\sprintf('Could not locate filter "%s" for path "%s". Story was "%s"', $filter, $path, $e->getMessage()));

            throw $e;
        }
    }
}
