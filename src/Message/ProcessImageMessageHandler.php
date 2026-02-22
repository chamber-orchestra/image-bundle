<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Message;

use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class ProcessImageMessageHandler
{
    public function __construct(
        private FilterService $filterService,
        private ?LockFactory $lockFactory = null,
        private int $concurrencyLimit = 0,
    ) {
    }

    public function __invoke(ProcessImageMessage $message): void
    {
        if ($this->concurrencyLimit > 0 && null !== $this->lockFactory) {
            $this->processWithConcurrencyLimit($message);

            return;
        }

        $this->process($message);
    }

    private function process(ProcessImageMessage $message): void
    {
        $this->filterService->getProcessedImageUrl(
            $message->path,
            $message->filter,
            $message->config,
            $message->resolver,
        );
    }

    private function processWithConcurrencyLimit(ProcessImageMessage $message): void
    {
        $lock = $this->acquireSlot();

        if (null === $lock) {
            throw new RecoverableMessageHandlingException('All image processing slots are occupied.');
        }

        try {
            $this->process($message);
        } finally {
            $lock->release();
        }
    }

    private function acquireSlot(): ?SharedLockInterface
    {
        if (null === $this->lockFactory) {
            throw new \LogicException('A LockFactory is required when concurrencyLimit > 0. Install symfony/lock.');
        }

        for ($i = 0; $i < $this->concurrencyLimit; ++$i) {
            $lock = $this->lockFactory->createLock(\sprintf('image_processing_slot_%d', $i), ttl: 600, autoRelease: true);

            if ($lock->acquire(blocking: false)) {
                return $lock;
            }
        }

        return null;
    }
}
