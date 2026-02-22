<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Service;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessage;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class FilterServiceTest extends TestCase
{
    private DataManager $dataManager;
    private FilterManager $filterManager;
    private CacheManager $cacheManager;
    private FilterConfiguration $filterConfig;

    protected function setUp(): void
    {
        $this->dataManager = $this->createMock(DataManager::class);
        $this->filterManager = $this->createMock(FilterManager::class);
        $this->cacheManager = $this->createMock(CacheManager::class);
        $this->filterConfig = new FilterConfiguration([
            'thumbnail' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'test-secret',
                'async' => false,
                'processors' => [],
                'post_processors' => [],
            ],
            'async_filter' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'test-secret',
                'async' => true,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);
    }

    #[Test]
    public function processImageReturnsCachedUrl(): void
    {
        $this->cacheManager->method('getPath')->willReturn('cache/moonlight_sonata.jpg');
        $this->cacheManager->method('isStored')->willReturn(true);
        $this->cacheManager->method('getBrowserPath')->willReturn('/media/cache/thumbnail/moonlight_sonata.jpg');

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
        );

        $result = $service->processImage('scores/moonlight_sonata.jpg', 'thumbnail', []);

        self::assertFalse($result->async);
        self::assertSame('/media/cache/thumbnail/moonlight_sonata.jpg', $result->url);
        self::assertNull($result->binary);
    }

    #[Test]
    public function processImageDispatchesMessageWhenAsyncEnabled(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/symphony_no_5.png');
        $this->cacheManager->method('isStored')->willReturn(false);

        $this->dataManager
            ->expects($this->once())
            ->method('find')
            ->with('async_filter', 'scores/symphony_no_5.png')
            ->willReturn($binary);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ProcessImageMessage $message): bool {
                return 'scores/symphony_no_5.png' === $message->path
                    && 'async_filter' === $message->filter
                    && [] === $message->config
                    && null === $message->resolver;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            $messageBus,
        );

        $result = $service->processImage('scores/symphony_no_5.png', 'async_filter', []);

        self::assertTrue($result->async);
        self::assertSame($binary, $result->binary);
        self::assertNull($result->url);
    }

    #[Test]
    public function processImageFallsBackToSyncWhenNoMessenger(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $filtered = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/violin_concerto.jpg');
        $this->cacheManager->method('isStored')->willReturn(false);
        $this->cacheManager->method('getBrowserPath')->willReturn('/media/cache/async_filter/violin_concerto.jpg');

        $this->dataManager->method('find')->willReturn($binary);
        $this->filterManager->method('applyFilter')->willReturn($filtered);

        $this->cacheManager
            ->expects($this->once())
            ->method('store')
            ->with($filtered, 'cache/violin_concerto.jpg', 'async_filter', null);

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            null,
        );

        $result = $service->processImage('scores/violin_concerto.jpg', 'async_filter', []);

        self::assertFalse($result->async);
        self::assertSame('/media/cache/async_filter/violin_concerto.jpg', $result->url);
    }

    #[Test]
    public function processImageSyncPathWhenAsyncDisabled(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $filtered = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/nocturne.jpg');
        $this->cacheManager->method('isStored')->willReturn(false);
        $this->cacheManager->method('getBrowserPath')->willReturn('/media/cache/thumbnail/nocturne.jpg');

        $this->dataManager->method('find')->willReturn($binary);
        $this->filterManager->method('applyFilter')->willReturn($filtered);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            $messageBus,
        );

        $result = $service->processImage('scores/nocturne.jpg', 'thumbnail', []);

        self::assertFalse($result->async);
        self::assertSame('/media/cache/thumbnail/nocturne.jpg', $result->url);
    }

    #[Test]
    public function processImageSkipsDispatchWhenLockAlreadyHeld(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/prelude_in_c.jpg');
        $this->cacheManager->method('isStored')->willReturn(false);
        $this->dataManager->method('find')->willReturn($binary);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(false)->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            $messageBus,
            $lockFactory,
        );

        $result = $service->processImage('scores/prelude_in_c.jpg', 'async_filter', []);

        self::assertTrue($result->async);
        self::assertSame($binary, $result->binary);
        self::assertNull($result->url);
    }

    #[Test]
    public function processImageDispatchesWhenLockAcquired(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/fugue_in_g.jpg');
        $this->cacheManager->method('isStored')->willReturn(false);
        $this->dataManager->method('find')->willReturn($binary);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(false)->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            $messageBus,
            $lockFactory,
        );

        $result = $service->processImage('scores/fugue_in_g.jpg', 'async_filter', []);

        self::assertTrue($result->async);
    }

    #[Test]
    public function processImageDispatchesWithoutLockFactory(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $this->cacheManager->method('getPath')->willReturn('cache/etude_op10.jpg');
        $this->cacheManager->method('isStored')->willReturn(false);
        $this->dataManager->method('find')->willReturn($binary);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $service = new FilterService(
            $this->dataManager,
            $this->filterManager,
            $this->cacheManager,
            $this->filterConfig,
            $messageBus,
            null,
        );

        $result = $service->processImage('scores/etude_op10.jpg', 'async_filter', []);

        self::assertTrue($result->async);
        self::assertSame($binary, $result->binary);
    }
}
