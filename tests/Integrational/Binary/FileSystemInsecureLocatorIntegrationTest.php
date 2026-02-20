<?php

declare(strict_types=1);

namespace Tests\Integrational\Binary;

use ChamberOrchestra\ImageBundle\Binary\Locator\FileSystemInsecureLocator;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileSystemInsecureLocatorIntegrationTest extends TestCase
{
    private string $rootDir;
    private FileSystemInsecureLocator $locator;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/image_bundle_insecure_'.uniqid();
        mkdir($this->rootDir, 0755, true);

        $this->locator = new FileSystemInsecureLocator();
        $this->locator->setOptions(['roots' => [$this->rootDir]]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    #[Test]
    public function locateFindsFileInRoot(): void
    {
        file_put_contents($this->rootDir.'/image.jpg', 'jpeg-data');

        $result = $this->locator->locate('image.jpg');

        self::assertSame(realpath($this->rootDir.'/image.jpg'), $result);
    }

    #[Test]
    public function locateRejectsDoubleDotTraversal(): void
    {
        $this->expectException(NotLoadableException::class);

        $this->locator->locate('../../../etc/passwd');
    }

    #[Test]
    public function locateRejectsDoubleDotInMiddle(): void
    {
        mkdir($this->rootDir.'/sub', 0755, true);
        file_put_contents($this->rootDir.'/secret.jpg', 'secret-data');

        $this->expectException(NotLoadableException::class);

        $this->locator->locate('sub/../secret.jpg');
    }

    #[Test]
    public function locateRejectsSymlinksEscapingRoot(): void
    {
        $outsideFile = sys_get_temp_dir().'/outside_'.uniqid().'.jpg';
        file_put_contents($outsideFile, 'outside-data');
        $symlinkPath = $this->rootDir.'/symlink.jpg';
        symlink($outsideFile, $symlinkPath);

        try {
            // The insecure locator with realpath+prefix check should reject symlinks pointing outside root
            $this->expectException(NotLoadableException::class);
            $this->locator->locate('symlink.jpg');
        } finally {
            unlink($symlinkPath);
            unlink($outsideFile);
        }
    }

    #[Test]
    public function getNameReturnsFilesystemInsecure(): void
    {
        self::assertSame('filesystem_insecure', $this->locator->getName());
    }

    #[Test]
    public function locateThrowsForNonExistentFile(): void
    {
        $this->expectException(NotLoadableException::class);

        $this->locator->locate('nonexistent.jpg');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
