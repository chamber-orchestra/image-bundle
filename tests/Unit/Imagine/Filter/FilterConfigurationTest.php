<?php

declare(strict_types=1);

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
}
