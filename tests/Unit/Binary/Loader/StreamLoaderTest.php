<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Binary\Loader;

use ChamberOrchestra\ImageBundle\Binary\Loader\StreamLoader;
use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StreamLoaderTest extends TestCase
{
    #[Test]
    public function findReturnsContentFromDataWrapper(): void
    {
        $loader = new StreamLoader('data://text/plain,');

        $result = $loader->find('hello-world');

        self::assertSame('hello-world', $result);
    }

    #[Test]
    public function findThrowsNotLoadableExceptionWhenStreamNotFound(): void
    {
        $loader = new StreamLoader('nonexistent-scheme://');

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/could not be loaded/i');

        $loader->find('missing-file');
    }

    #[Test]
    public function constructorThrowsInvalidArgumentExceptionForInvalidContext(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no valid resource/i');

        new StreamLoader('data://', 'not-a-resource');
    }

    #[Test]
    public function constructorAcceptsNullContext(): void
    {
        $loader = new StreamLoader('data://text/plain,', null);

        $result = $loader->find('test');

        self::assertSame('test', $result);
    }

    #[Test]
    public function findUsesStreamContextWhenProvided(): void
    {
        $context = \stream_context_create([]);
        $loader = new StreamLoader('data://text/plain,', $context);

        $result = $loader->find('with-context');

        self::assertSame('with-context', $result);
    }

    #[Test]
    public function getNameReturnsStream(): void
    {
        $loader = new StreamLoader('data://');

        self::assertSame('stream', $loader->getName());
    }

    #[Test]
    public function findPrefixesPathWithWrapperPrefix(): void
    {
        $loader = new StreamLoader('data://text/plain,prefix-');

        $result = $loader->find('suffix');

        self::assertSame('prefix-suffix', $result);
    }
}
