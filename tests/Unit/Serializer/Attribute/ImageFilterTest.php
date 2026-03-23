<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Serializer\Attribute;

use ChamberOrchestra\ImageBundle\Serializer\Attribute\ImageFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ImageFilterTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attr = new ImageFilter();

        self::assertSame('default', $attr->key);
        self::assertSame('fill', $attr->filter);
        self::assertSame(0, $attr->width);
        self::assertSame(0, $attr->height);
        self::assertSame('default', $attr->preset);
    }

    #[Test]
    public function customValues(): void
    {
        $attr = new ImageFilter(
            key: 'hero',
            filter: 'fit',
            width: 800,
            height: 400,
            preset: 'high_quality',
        );

        self::assertSame('hero', $attr->key);
        self::assertSame('fit', $attr->filter);
        self::assertSame(800, $attr->width);
        self::assertSame(400, $attr->height);
        self::assertSame('high_quality', $attr->preset);
    }

    #[Test]
    public function attributeIsRepeatableOnProperty(): void
    {
        $reflection = new \ReflectionClass(ImageFilter::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $flags = $attributes[0]->newInstance()->flags;
        self::assertNotSame(0, $flags & \Attribute::IS_REPEATABLE);
        self::assertNotSame(0, $flags & \Attribute::TARGET_PROPERTY);
    }

    #[Test]
    public function multipleAttributesOnSamePropertyResolveDistinctKeys(): void
    {
        $class = new class {
            #[ImageFilter(key: 'thumbnail', filter: 'fill', width: 100, height: 100)]
            #[ImageFilter(key: 'banner', filter: 'fit', width: 1200, height: 400)]
            public ?string $concertPoster = null;
        };

        $property = new \ReflectionProperty($class, 'concertPoster');
        $attrs = $property->getAttributes(ImageFilter::class);

        self::assertCount(2, $attrs);
        self::assertSame('thumbnail', $attrs[0]->newInstance()->key);
        self::assertSame('banner', $attrs[1]->newInstance()->key);
    }
}
