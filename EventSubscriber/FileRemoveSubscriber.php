<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\EventSubscriber;

use ChamberOrchestra\FileBundle\Events\PostRemoveEvent;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(PostRemoveEvent::class)]
readonly class FileRemoveSubscriber
{
    public function __construct(private CacheManager $manager)
    {
    }

    public function __invoke(PostRemoveEvent $event): void
    {
        $this->manager->remove($event->resolvedUri);
    }
}