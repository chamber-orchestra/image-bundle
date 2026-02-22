<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Service;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class FilterService
{
    public function __construct(
        private DataManager $dataManager,
        private FilterManager $filterManager,
        private CacheManager $cacheManager,
        private FilterConfiguration $filterConfig,
        private ?MessageBusInterface $messageBus = null,
        private ?LockFactory $lockFactory = null,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function processImage(string $path, string $filter, array $config, ?string $resolver = null): FilterResult
    {
        $cachePath = $this->cacheManager->getPath($path, $config, $filter);

        if ($this->cacheManager->isStored($cachePath, $filter, $resolver)) {
            return FilterResult::resolved($this->cacheManager->getBrowserPath($path, $filter, $config, $resolver));
        }

        if ($this->isAsync($filter) && null !== $this->messageBus) {
            $binary = $this->dataManager->find($filter, $path);

            if ($this->acquireDispatchLock($cachePath)) {
                $this->messageBus->dispatch(new ProcessImageMessage($path, $filter, $config, $resolver));
            }

            return FilterResult::deferred($binary);
        }

        return FilterResult::resolved($this->getProcessedImageUrl($path, $filter, $config, $resolver));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getProcessedImageUrl(string $path, string $filter, array $config, ?string $resolver = null): string
    {
        $cachePath = $this->cacheManager->getPath($path, $config, $filter);
        if (!$this->cacheManager->isStored($cachePath, $filter, $resolver)) {
            $this->cacheManager->store($this->createFilteredBinary($path, $filter, $config), $cachePath, $filter, $resolver);
        }

        return $this->cacheManager->getBrowserPath($path, $filter, $config, $resolver);
    }

    private function acquireDispatchLock(string $cachePath): bool
    {
        if (null === $this->lockFactory) {
            return true;
        }

        $lock = $this->lockFactory->createLock('image_dispatch_'.\sha1($cachePath), ttl: 300, autoRelease: false);

        return $lock->acquire(blocking: false);
    }

    private function isAsync(string $filter): bool
    {
        $config = $this->filterConfig->get($filter);

        return !empty($config['async']);
    }

    public function getDefaultImageUrl(string $filter): ?string
    {
        return $this->dataManager->getDefaultImageUrl($filter);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws NonExistingFilterException
     */
    public function createFilteredBinary(string $path, string $filter, array $config = []): BinaryInterface
    {
        $binary = $this->dataManager->find($filter, $path);

        try {
            return $this->filterManager->applyFilter($binary, $filter, $config);
        } catch (NonExistingFilterException $e) {
            $this->logger->debug(\sprintf('Could not locate filter "%s" for path "%s". Story was "%s"', $filter, $path, $e->getMessage()));

            throw $e;
        }
    }
}
