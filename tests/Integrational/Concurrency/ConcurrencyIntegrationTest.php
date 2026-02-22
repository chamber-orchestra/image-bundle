<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Concurrency;

use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\WebPathResolver;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterManager;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessage;
use ChamberOrchestra\ImageBundle\Message\ProcessImageMessageHandler;
use ChamberOrchestra\ImageBundle\Model\Binary;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class ConcurrencyIntegrationTest extends TestCase
{
    private string $webRoot;
    private Filesystem $filesystem;
    private FilterConfiguration $filterConfig;
    private LockFactory $lockFactory;

    protected function setUp(): void
    {
        $this->webRoot = \sys_get_temp_dir().'/image_bundle_concurrency_'.\uniqid();
        \mkdir($this->webRoot, 0755, true);

        $this->filesystem = new Filesystem();
        $this->lockFactory = new LockFactory(new InMemoryStore());

        $this->filterConfig = new FilterConfiguration([
            'aria' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'aria-concurrency-secret',
                'async' => true,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
            'nocturne' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 'nocturne-concurrency-secret',
                'async' => true,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->webRoot);
    }

    #[Test]
    public function firstDispatchAcquiresLockAndSendsMessage(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $filterService = $this->buildFilterService($messageBus, $this->lockFactory);

        $result = $filterService->processImage('scores/moonlight.jpg', 'aria', []);

        self::assertTrue($result->async);
        self::assertNotNull($result->binary);
    }

    #[Test]
    public function secondDispatchForSamePathSkipsMessageBus(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $filterService = $this->buildFilterService($messageBus, $this->lockFactory);

        $filterService->processImage('scores/moonlight.jpg', 'aria', []);
        $result = $filterService->processImage('scores/moonlight.jpg', 'aria', []);

        self::assertTrue($result->async);
    }

    #[Test]
    public function differentPathsCanBothDispatch(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $filterService = $this->buildFilterService($messageBus, $this->lockFactory);

        $filterService->processImage('scores/moonlight.jpg', 'aria', []);
        $filterService->processImage('scores/concerto.jpg', 'aria', []);
    }

    #[Test]
    public function dispatchWithoutLockFactoryAlwaysSends(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $filterService = $this->buildFilterService($messageBus, null);

        $filterService->processImage('scores/moonlight.jpg', 'aria', []);
        $filterService->processImage('scores/moonlight.jpg', 'aria', []);
    }

    #[Test]
    public function handlerAcquiresSlotAndProcesses(): void
    {
        $filterService = $this->buildFilterService(null, null);
        $handler = new ProcessImageMessageHandler($filterService, $this->lockFactory, concurrencyLimit: 2);

        $message = new ProcessImageMessage('scores/moonlight.jpg', 'aria', []);

        // Should not throw — acquires a slot and processes
        $handler($message);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handlerWithAllSlotsOccupiedThrowsRecoverable(): void
    {
        $filterService = $this->buildFilterService(null, null);
        $handler = new ProcessImageMessageHandler($filterService, $this->lockFactory, concurrencyLimit: 2);

        // Pre-acquire both slots
        $lock0 = $this->lockFactory->createLock('image_processing_slot_0', ttl: 600, autoRelease: true);
        $lock1 = $this->lockFactory->createLock('image_processing_slot_1', ttl: 600, autoRelease: true);
        $lock0->acquire();
        $lock1->acquire();

        $this->expectException(RecoverableMessageHandlingException::class);
        $this->expectExceptionMessageMatches('/slots are occupied/');

        $handler(new ProcessImageMessage('scores/moonlight.jpg', 'aria', []));
    }

    #[Test]
    public function handlerReleasesSlotAfterProcessing(): void
    {
        $filterService = $this->buildFilterService(null, null);
        $handler = new ProcessImageMessageHandler($filterService, $this->lockFactory, concurrencyLimit: 1);

        $message = new ProcessImageMessage('scores/moonlight.jpg', 'aria', []);

        // Process once — acquires and releases slot
        $handler($message);

        // Process again — should succeed because slot was released
        $handler($message);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handlerTriesNextSlotWhenFirstOccupied(): void
    {
        $filterService = $this->buildFilterService(null, null);
        $handler = new ProcessImageMessageHandler($filterService, $this->lockFactory, concurrencyLimit: 2);

        // Pre-acquire slot 0
        $lock0 = $this->lockFactory->createLock('image_processing_slot_0', ttl: 600, autoRelease: true);
        $lock0->acquire();

        // Handler should acquire slot 1 and succeed
        $handler(new ProcessImageMessage('scores/moonlight.jpg', 'aria', []));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handlerWithZeroConcurrencyLimitSkipsLocking(): void
    {
        $filterService = $this->buildFilterService(null, null);
        $handler = new ProcessImageMessageHandler($filterService, $this->lockFactory, concurrencyLimit: 0);

        // Should process directly without any locking
        $handler(new ProcessImageMessage('scores/moonlight.jpg', 'aria', []));

        $this->addToAssertionCount(1);
    }

    private function buildFilterService(?MessageBusInterface $messageBus, ?LockFactory $lockFactory): FilterService
    {
        $signer = new Signer();

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/_media/test');

        $cacheManager = new CacheManager($this->filterConfig, $router, $signer);

        $resolver = new WebPathResolver(
            $this->filesystem,
            new RequestContext(),
            $this->webRoot,
            'media/cache',
        );
        $cacheManager->addResolver($resolver, 'default');

        $dataManager = $this->createMock(DataManager::class);
        $dataManager->method('find')->willReturn(new Binary('raw-image', 'image/jpeg', 'jpeg'));

        $filterManager = $this->createMock(FilterManager::class);
        $filterManager->method('applyFilter')->willReturn(new Binary('filtered-image', 'image/jpeg', 'jpeg'));

        return new FilterService(
            $dataManager,
            $filterManager,
            $cacheManager,
            $this->filterConfig,
            $messageBus,
            $lockFactory,
        );
    }
}
