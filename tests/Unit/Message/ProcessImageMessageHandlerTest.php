<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Message;

use ChamberOrchestra\ImageBundle\Message\ProcessImageMessage;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessageHandler;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

class ProcessImageMessageHandlerTest extends TestCase
{
    #[Test]
    public function invokeDelegatestoFilterService(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService
            ->expects($this->once())
            ->method('getProcessedImageUrl')
            ->with('scores/moonlight_sonata.jpg', 'thumbnail', ['fit' => ['width' => 300]], 'cdn');

        $handler = new ProcessImageMessageHandler($filterService);
        $handler(new ProcessImageMessage('scores/moonlight_sonata.jpg', 'thumbnail', ['fit' => ['width' => 300]], 'cdn'));
    }

    #[Test]
    public function invokePassesDefaultsWhenConfigAndResolverOmitted(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService
            ->expects($this->once())
            ->method('getProcessedImageUrl')
            ->with('scores/symphony_no_5.png', 'avatar', [], null);

        $handler = new ProcessImageMessageHandler($filterService);
        $handler(new ProcessImageMessage('scores/symphony_no_5.png', 'avatar'));
    }

    #[Test]
    public function invokeProcessesWithoutConcurrencyLimitWhenDisabled(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->once())->method('getProcessedImageUrl');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->never())->method('createLock');

        $handler = new ProcessImageMessageHandler($filterService, $lockFactory, 0);
        $handler(new ProcessImageMessage('scores/waltz_in_a.jpg', 'thumbnail'));
    }

    #[Test]
    public function invokeAcquiresSlotAndProcessesWhenAvailable(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->once())->method('getProcessedImageUrl');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(false)->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')
            ->with('image_processing_slot_0', 600, true)
            ->willReturn($lock);

        $handler = new ProcessImageMessageHandler($filterService, $lockFactory, 2);
        $handler(new ProcessImageMessage('scores/rondo_alla_turca.jpg', 'thumbnail'));
    }

    #[Test]
    public function invokeTriesNextSlotWhenFirstOccupied(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->once())->method('getProcessedImageUrl');

        $occupiedLock = $this->createMock(SharedLockInterface::class);
        $occupiedLock->method('acquire')->with(false)->willReturn(false);

        $freeLock = $this->createMock(SharedLockInterface::class);
        $freeLock->expects($this->once())->method('acquire')->with(false)->willReturn(true);
        $freeLock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->exactly(2))->method('createLock')
            ->willReturnCallback(function (string $resource) use ($occupiedLock, $freeLock): SharedLockInterface {
                return 'image_processing_slot_0' === $resource ? $occupiedLock : $freeLock;
            });

        $handler = new ProcessImageMessageHandler($filterService, $lockFactory, 2);
        $handler(new ProcessImageMessage('scores/ballade_no_1.jpg', 'thumbnail'));
    }

    #[Test]
    public function invokeThrowsRecoverableExceptionWhenAllSlotsOccupied(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->never())->method('getProcessedImageUrl');

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->with(false)->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $handler = new ProcessImageMessageHandler($filterService, $lockFactory, 2);

        $this->expectException(RecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('All image processing slots are occupied.');

        $handler(new ProcessImageMessage('scores/polonaise_op53.jpg', 'thumbnail'));
    }

    #[Test]
    public function invokeReleasesLockWhenProcessingFails(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->method('getProcessedImageUrl')
            ->willThrowException(new \RuntimeException('Processing failed'));

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $handler = new ProcessImageMessageHandler($filterService, $lockFactory, 1);

        $this->expectException(\RuntimeException::class);
        $handler(new ProcessImageMessage('scores/scherzo_no_2.jpg', 'thumbnail'));
    }

    #[Test]
    public function invokeProcessesWithoutLockingWhenLockFactoryNull(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->once())->method('getProcessedImageUrl');

        $handler = new ProcessImageMessageHandler($filterService, null, 4);
        $handler(new ProcessImageMessage('scores/mazurka_op7.jpg', 'thumbnail'));
    }
}
