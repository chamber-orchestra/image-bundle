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
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AvifPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [
        'quality' => 63,
        'speed' => 6,
        'timeout' => 60,
    ];
    private string $tempDir;

    /**
     * @param string               $bin     Path to the avifenc binary
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $bin = '/usr/local/bin/avifenc',
        array $options = [],
    ) {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive($this->options, $options);
        $this->options = $merged;
        $this->tempDir = \sys_get_temp_dir();
    }

    #[\Override]
    public static function getIndexName(): string
    {
        return 'avifenc';
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function process(BinaryInterface $binary, array $options): BinaryInterface
    {
        if (!\is_executable($this->bin)) {
            return $binary;
        }

        $type = \strtolower($binary->getMimeType());
        $supported = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/tiff'];
        if (!\in_array($type, $supported, true)) {
            return $binary;
        }

        /** @var array<string, mixed> $options */
        $options = \array_replace_recursive($this->options, $options);

        $cleanupInput = false;
        $output = null;

        if ($binary instanceof FileBinaryInterface) {
            $input = $binary->getPath();
        } else {
            if (false === $input = \tempnam($this->tempDir, 'imagine_avifenc')) {
                throw new RuntimeException(\sprintf('Temp file can not be created in "%s".', $this->tempDir));
            }
            $cleanupInput = true;
            $content = $binary->getContent();
            if (\file_put_contents($input, $content) !== \strlen($content)) {
                throw new RuntimeException(\sprintf('Could not write image content to temp file "%s".', $input));
            }
        }

        try {
            if (false === $output = \tempnam($this->tempDir, 'imagine_avifenc_out')) {
                throw new RuntimeException(\sprintf('Temp file can not be created in "%s".', $this->tempDir));
            }

            /** @var int|string $rawQuality */
            $rawQuality = $options['quality'];
            /** @var int|string $rawSpeed */
            $rawSpeed = $options['speed'];
            /** @var int|string $rawTimeout */
            $rawTimeout = $options['timeout'];

            $quality = \max(0, \min(100, (int) $rawQuality));
            $speed = \max(0, \min(10, (int) $rawSpeed));
            $timeout = \max(1, (int) $rawTimeout);

            $command = [$this->bin, '-q', (string) $quality, '-s', (string) $speed, $input, '-o', $output];

            $process = new Process($command, null, null, null, (float) $timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $content = \file_get_contents($output);
            if (false === $content) {
                throw new RuntimeException(\sprintf('Could not read avifenc output file "%s".', $output));
            }

            return new Binary($content, 'image/avif', 'avif');
        } finally {
            if ($cleanupInput) {
                \unlink($input);
            }
            if (\is_string($output) && \file_exists($output)) {
                \unlink($output);
            }
        }
    }
}
