<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

interface CacheManagerAwareInterface
{
    /**
     * @param CacheManager $cacheManager
     */
    public function setCacheManager(CacheManager $cacheManager): void;
}
