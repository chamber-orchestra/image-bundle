<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        if (null !== $event->resolvedUri) {
            $this->manager->remove($event->resolvedUri);
        }
    }
}
