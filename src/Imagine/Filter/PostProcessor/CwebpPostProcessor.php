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

class CwebpPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [
        'quality' => 90,
        'timeout' => 30,
    ];
    private string $tempDir;

    /**
     * @param string               $bin     Path to the cwebp binary
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $bin = '/usr/local/bin/cwebp',
        private readonly ?string $ldPath = '/usr/local/lib',
        array $options = []
    ) {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive($this->options, $options);
        $this->options = $merged;
        $this->tempDir = \sys_get_temp_dir();
    }

    #[\Override]
    public static function getIndexName(): string
    {
        return 'cwebp';
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

        if ($binary instanceof FileBinaryInterface) {
            $input = $binary->getPath();
        } else {
            if (false === $input = \tempnam($this->tempDir, 'imagine_cwebp')) {
                throw new RuntimeException(\sprintf('Temp file can not be created in "%s".', $this->tempDir));
            }
            $cleanupInput = true;
            $content = $binary->getContent();
            if (\file_put_contents($input, $content) !== \strlen($content)) {
                throw new RuntimeException(\sprintf('Could not write image content to temp file "%s".', $input));
            }
        }

        try {
            /** @var int|string $rawQuality */
            $rawQuality = $options['quality'];
            /** @var int|string $rawTimeout */
            $rawTimeout = $options['timeout'];

            $quality = \max(0, \min(100, (int) $rawQuality));
            $timeout = \max(1, (int) $rawTimeout);

            $command = [$this->bin, '-q', (string) $quality, '-quiet', $input, '-o', '-'];

            $env = $this->ldPath ? ['LD_LIBRARY_PATH' => $this->ldPath] : null;
            $process = new Process($command, null, $env, null, (float) $timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return new Binary($process->getOutput(), 'image/webp', 'webp');
        } finally {
            if ($cleanupInput) {
                \unlink($input);
            }
        }
    }
}
