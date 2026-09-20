<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Filter;

use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\AvifPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\CwebpPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\MozJpegPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor\PngquantPostProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FillProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\FitProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\InterlaceProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\OptimizeProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\OutputProcessor;
use ChamberOrchestra\ImageBundle\Imagine\Filter\Processor\StripProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The #[AsTaggedItem] index is the key users write in `filters.<name>.processors`
 * and `filters.<name>.post_processors`, so changing one is a BC break.
 */
class TaggedItemIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function indexProvider(): iterable
    {
        yield 'fill' => [FillProcessor::class, 'fill'];
        yield 'fit' => [FitProcessor::class, 'fit'];
        yield 'interlace' => [InterlaceProcessor::class, 'interlace'];
        yield 'optimize' => [OptimizeProcessor::class, 'optimize'];
        yield 'output' => [OutputProcessor::class, 'output'];
        yield 'strip' => [StripProcessor::class, 'strip'];
        yield 'avifenc' => [AvifPostProcessor::class, 'avifenc'];
        yield 'cwebp' => [CwebpPostProcessor::class, 'cwebp'];
        yield 'mozjpeg' => [MozJpegPostProcessor::class, 'mozjpeg'];
        yield 'pngquant' => [PngquantPostProcessor::class, 'pngquant'];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('indexProvider')]
    public function processorDeclaresItsTaggedItemIndex(string $class, string $expectedIndex): void
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(AsTaggedItem::class);

        self::assertCount(1, $attributes, \sprintf('"%s" must carry exactly one #[AsTaggedItem].', $class));
        self::assertSame($expectedIndex, $attributes[0]->newInstance()->index);
    }
}
