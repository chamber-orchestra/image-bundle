<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Integrational\Binary;

use ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemLocator;
use ChamberOrchestra\ImageBundle\Exception\InvalidArgumentException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileSystemLocatorIntegrationTest extends TestCase
{
    private string $rootDir;
    private FileSystemLocator $locator;

    protected function setUp(): void
    {
        $this->rootDir = \sys_get_temp_dir().'/image_bundle_locator_'.\uniqid();
        \mkdir($this->rootDir, 0755, true);

        $this->locator = new FileSystemLocator();
        $this->locator->setOptions(['roots' => [$this->rootDir]]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    #[Test]
    public function locateFindsFileInRoot(): void
    {
        \file_put_contents($this->rootDir.'/photo.jpg', 'image-data');

        $result = $this->locator->locate('photo.jpg');

        self::assertSame(\realpath($this->rootDir.'/photo.jpg'), $result);
    }

    #[Test]
    public function locateFindsFileInSubdirectory(): void
    {
        \mkdir($this->rootDir.'/sub', 0755, true);
        \file_put_contents($this->rootDir.'/sub/photo.jpg', 'image-data');

        $result = $this->locator->locate('sub/photo.jpg');

        self::assertSame(\realpath($this->rootDir.'/sub/photo.jpg'), $result);
    }

    #[Test]
    public function locateThrowsForNonExistentFile(): void
    {
        $this->expectException(NotLoadableException::class);

        $this->locator->locate('nonexistent.jpg');
    }

    #[Test]
    public function locateStripsLeadingSlashFromPath(): void
    {
        // file-bundle stores paths with a leading slash e.g. /uploads/photo.jpg
        \file_put_contents($this->rootDir.'/photo.jpg', 'image-data');

        $result = $this->locator->locate('/photo.jpg');

        self::assertSame(\realpath($this->rootDir.'/photo.jpg'), $result);
    }

    #[Test]
    public function locateThrowsForPathOutsideRoot(): void
    {
        // Create a file in /tmp directly (outside the root)
        $outsideFile = \sys_get_temp_dir().'/outside.jpg';
        \file_put_contents($outsideFile, 'image-data');

        try {
            $this->expectException(NotLoadableException::class);
            $this->locator->locate($outsideFile);
        } finally {
            \unlink($outsideFile);
        }
    }

    #[Test]
    public function locateThrowsForTraversalAttempt(): void
    {
        $this->expectException(NotLoadableException::class);

        $this->locator->locate('../../../etc/passwd');
    }

    #[Test]
    public function setOptionsThrowsForNonExistentRoot(): void
    {
        $locator = new FileSystemLocator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Root image path not resolvable/');

        $locator->setOptions(['roots' => ['/nonexistent/directory']]);
    }

    #[Test]
    public function locateSupportsRootPlaceholder(): void
    {
        $namedLocator = new FileSystemLocator();
        $namedLocator->setOptions(['roots' => ['images' => $this->rootDir]]);

        \file_put_contents($this->rootDir.'/photo.jpg', 'image-data');

        $result = $namedLocator->locate('@images:photo.jpg');

        self::assertSame(\realpath($this->rootDir.'/photo.jpg'), $result);
    }

    #[Test]
    public function locateThrowsForInvalidRootPlaceholder(): void
    {
        $namedLocator = new FileSystemLocator();
        $namedLocator->setOptions(['roots' => ['images' => $this->rootDir]]);

        $this->expectException(NotLoadableException::class);
        $this->expectExceptionMessageMatches('/Invalid root placeholder/');

        $namedLocator->locate('@nonexistent:photo.jpg');
    }

    #[Test]
    public function getNameReturnsFilesystem(): void
    {
        self::assertSame('filesystem', $this->locator->getName());
    }

    #[Test]
    public function locateSearchesMultipleRoots(): void
    {
        $secondRoot = \sys_get_temp_dir().'/image_bundle_root2_'.\uniqid();
        \mkdir($secondRoot, 0755, true);
        \file_put_contents($secondRoot.'/from-second.jpg', 'image-data');

        try {
            $locator = new FileSystemLocator();
            $locator->setOptions(['roots' => [$this->rootDir, $secondRoot]]);

            $result = $locator->locate('from-second.jpg');

            self::assertSame(\realpath($secondRoot.'/from-second.jpg'), $result);
        } finally {
            \unlink($secondRoot.'/from-second.jpg');
            \rmdir($secondRoot);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;

            \is_dir($path) ? $this->removeDir($path) : \unlink($path);
        }

        \rmdir($dir);
    }
}
