<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational;

trait FixtureImageTrait
{
    private static function fixtureDir(): string
    {
        return \dirname(__DIR__).'/Fixtures';
    }

    private static function jpegFixturePath(): string
    {
        return self::fixtureDir().'/moonlight_sonata.jpg';
    }

    private static function pngFixturePath(): string
    {
        return self::fixtureDir().'/symphony_no_5.png';
    }
}
