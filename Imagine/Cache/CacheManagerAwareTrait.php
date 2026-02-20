<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

trait CacheManagerAwareTrait
{
    protected CacheManager $cacheManager;

    public function setCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }
}
