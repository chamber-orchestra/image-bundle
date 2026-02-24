<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MozJpegPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [
        'quality' => 75,
        'timeout' => 60,
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $bin = '/opt/mozjpeg/bin/cjpeg',
        array $options = [])
    {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive($this->options, $options);
        $this->options = $merged;
    }

    #[\Override]
    public static function getIndexName(): string
    {
        return 'mozjpeg';
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function process(BinaryInterface $binary, array $options): BinaryInterface
    {
        $type = \strtolower($binary->getMimeType());
        if (!\in_array($type, ['image/jpeg', 'image/jpg'], true)) {
            return $binary;
        }

        $command = [$this->bin, '-quant-table', 2, '-optimise'];

        /** @var array<string, mixed> $options */
        $options = \array_replace_recursive($this->options, $options);

        /** @var int|string $rawQuality */
        $rawQuality = $options['quality'];
        /** @var int|string $rawTimeout */
        $rawTimeout = $options['timeout'];

        $quality = \max(0, \min(100, (int) $rawQuality));
        $timeout = \max(1, (int) $rawTimeout);

        $command[] = '-quality';
        $command[] = (string) $quality;

        // Favor stdin/stdout so we don't waste time creating a new file.
        $process = new Process($command, null, null, null, (float) $timeout);
        $process->setInput($binary->getContent());

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if ('' === $process->getOutput()) {
            return $binary;
        }

        return new Binary($process->getOutput(), $binary->getMimeType(), $binary->getFormat());
    }
}
