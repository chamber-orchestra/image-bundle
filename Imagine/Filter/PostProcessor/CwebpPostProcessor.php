<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CwebpPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    private array $options = [
        'quality' => 90,
        'timeout' => 30,
    ];
    private string $tempDir;

    /**
     * @param string $bin Path to the pngquant binary
     * @param string $ldPath
     * @param array  $options
     */
    public function __construct(
        private readonly string $bin = '/usr/local/bin/cwebp',
        private readonly string|null $ldPath = '/usr/local/lib',
        array $options = []
    )
    {
        $this->options = \array_replace_recursive($this->options, $options);
        $this->tempDir = \sys_get_temp_dir();
    }

    public static function getIndexName(): string
    {
        return 'cwebp';
    }

    public function process(BinaryInterface $binary, array $options): BinaryInterface
    {
        $type = \strtolower($binary->getMimeType());
        $supported = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/tiff'];
        if (!\in_array($type, $supported, true)) {
            return $binary;
        }

        $options = \array_replace_recursive($this->options, $options);

        if (false === $input = \tempnam($this->tempDir, 'imagine_cwebp')) {
            throw new RuntimeException(\sprintf('Temp file can not be created in "%s".', $this->tempDir));
        }

        try {
            if ($binary instanceof FileBinaryInterface) {
                \copy($binary->getPath(), $input);
            } else {
                \file_put_contents($input, $binary->getContent());
            }

            $command = [$this->bin, '-q', $options['quality'], '-quiet', $input, '-o', '-'];

            $env = $this->ldPath ? ['LD_LIBRARY_PATH' => $this->ldPath] : null;
            $process = new Process($command, null, $env, null, $options['timeout']);
            $process->run();

            if (0 !== $process->getExitCode()) {
                throw new ProcessFailedException($process);
            }

            return new Binary($process->getOutput(), 'image/webp', 'webp');
        } finally {
            \unlink($input);
        }
    }
}
