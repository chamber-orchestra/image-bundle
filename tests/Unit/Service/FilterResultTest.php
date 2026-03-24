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
use ChamberOrchestra\ImageBundle\Service\FilterResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilterResultTest extends TestCase
{
    #[Test]
    public function resolvedSetsUrlAndNotAsync(): void
    {
        $result = FilterResult::resolved('/media/cache/thumbnail/moonlight_sonata.jpg');

        self::assertSame('/media/cache/thumbnail/moonlight_sonata.jpg', $result->url);
        self::assertNull($result->binary);
        self::assertFalse($result->async);
    }

    #[Test]
    public function deferredSetsBinaryAndAsync(): void
    {
        $binary = $this->createMock(BinaryInterface::class);

        $result = FilterResult::deferred($binary);

        self::assertNull($result->url);
        self::assertSame($binary, $result->binary);
        self::assertTrue($result->async);
    }
}
