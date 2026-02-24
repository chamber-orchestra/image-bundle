<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Enum;

enum ImageFormat: string
{
    case Png = 'png';
    case Jpg = 'jpg';
    case Jpeg = 'jpeg';
    case Webp = 'webp';
    case Gif = 'gif';
    case Tiff = 'tiff';
    case Bmp = 'bmp';
    case Avif = 'avif';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
