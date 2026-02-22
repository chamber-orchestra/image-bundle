<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require \dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/Fixtures/generate_fixtures.php';

\generateFixtureImages(__DIR__.'/Fixtures');

$envFile = \dirname(__DIR__).'/.env';

if (\is_file($envFile)) {
    foreach (\file($envFile, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) as $line) {
        if (\str_starts_with(\trim($line), '#')) {
            continue;
        }

        \putenv($line);
    }
}
