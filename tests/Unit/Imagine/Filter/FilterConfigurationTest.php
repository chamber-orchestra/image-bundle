<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterConfigurationTest extends TestCase
{
    #[Test]
    public function getReturnsFilterConfig(): void
    {
        $config = new FilterConfiguration([
            'thumbnail' => ['output' => ['quality' => 75]],
        ]);

        self::assertSame(['output' => ['quality' => 75]], $config->get('thumbnail'));
    }

    #[Test]
    public function getThrowsOnNonExistingFilter(): void
    {
        $config = new FilterConfiguration();

        $this->expectException(NonExistingFilterException::class);
        $config->get('non_existing');
    }

    #[Test]
    public function setAddsFilter(): void
    {
        $config = new FilterConfiguration();
        $config->set('thumbnail', ['output' => ['quality' => 80]]);

        self::assertSame(['output' => ['quality' => 80]], $config->get('thumbnail'));
    }

    #[Test]
    public function setOverwritesExistingFilter(): void
    {
        $config = new FilterConfiguration([
            'thumbnail' => ['output' => ['quality' => 75]],
        ]);

        $config->set('thumbnail', ['output' => ['quality' => 90]]);

        self::assertSame(['output' => ['quality' => 90]], $config->get('thumbnail'));
    }

    #[Test]
    public function allReturnsAllFilters(): void
    {
        $filters = [
            'thumbnail' => ['output' => ['quality' => 75]],
            'avatar' => ['output' => ['quality' => 80]],
        ];

        $config = new FilterConfiguration($filters);

        self::assertSame($filters, $config->all());
    }

    #[Test]
    public function allReturnsEmptyArrayWhenNoFilters(): void
    {
        $config = new FilterConfiguration();

        self::assertSame([], $config->all());
    }

    #[Test]
    public function asyncKeyIsPreserved(): void
    {
        $filters = [
            'thumbnail' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 's3cret',
                'async' => true,
                'exposed' => false,
                'processors' => [],
                'post_processors' => [],
            ],
        ];

        $config = new FilterConfiguration($filters);

        self::assertTrue($config->get('thumbnail')['async']);
    }

    #[Test]
    public function exposedKeyIsPreserved(): void
    {
        $filters = [
            'thumbnail' => [
                'resolver' => 'default',
                'loader' => 'default',
                'secret' => 's3cret',
                'async' => false,
                'exposed' => true,
                'processors' => [],
                'post_processors' => [],
            ],
        ];

        $config = new FilterConfiguration($filters);

        self::assertTrue($config->get('thumbnail')['exposed']);
    }
}
