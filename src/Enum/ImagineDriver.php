<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Enum;

enum ImagineDriver: string
{
    case Gd = 'Imagine\Gd\Imagine';
    case Imagick = 'Imagine\Imagick\Imagine';
    case Gmagick = 'Imagine\Gmagick\Imagine';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return \array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function fromAlias(string $alias): self
    {
        return match (\strtolower($alias)) {
            'gd' => self::Gd,
            'imagick' => self::Imagick,
            'gmagick' => self::Gmagick,
            default => self::from($alias),
        };
    }
}
