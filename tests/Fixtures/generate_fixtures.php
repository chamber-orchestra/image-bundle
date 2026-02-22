<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generates test fixture images with gradients for integration tests.
 * Called from tests/bootstrap.php — skips generation if files already exist.
 */
function generateFixtureImages(string $dir): void
{
    if (!\function_exists('imagecreatetruecolor')) {
        return;
    }

    $jpegPath = $dir.'/moonlight_sonata.jpg';
    $pngPath = $dir.'/symphony_no_5.png';

    if (\is_file($jpegPath) && \is_file($pngPath)) {
        return;
    }

    if (!\is_dir($dir)) {
        \mkdir($dir, 0755, true);
    }

    if (!\is_file($jpegPath)) {
        $img = \imagecreatetruecolor(800, 600);

        for ($x = 0; $x < 800; ++$x) {
            for ($y = 0; $y < 600; ++$y) {
                $r = \min(255, (int) (255 * $x / 800));
                $g = \min(255, (int) (255 * $y / 600));
                $b = \min(255, (int) (255 * (($x + $y) % 400) / 400));
                $color = \imagecolorallocate($img, $r, $g, $b);
                \imagesetpixel($img, $x, $y, $color);
            }
        }

        \imagejpeg($img, $jpegPath, 90);
        unset($img);
    }

    if (!\is_file($pngPath)) {
        $img = \imagecreatetruecolor(640, 480);

        for ($x = 0; $x < 640; ++$x) {
            for ($y = 0; $y < 480; ++$y) {
                $r = \min(255, (int) (255 * (640 - $x) / 640));
                $g = \min(255, (int) (128 + 127 * \sin(M_PI * $y / 480)));
                $b = \min(255, (int) (255 * $x / 640));
                $color = \imagecolorallocate($img, $r, $g, $b);
                \imagesetpixel($img, $x, $y, $color);
            }
        }

        \imagepng($img, $pngPath);
        unset($img);
    }
}
