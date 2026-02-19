<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MozJpegPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    private array $options = [
        'quality' => 75,
        'timeout' => 60,
    ];

    public function __construct(
        private readonly string $bin = '/opt/mozjpeg/bin/cjpeg',
        array $options = [])
    {
        $this->options = \array_replace_recursive($this->options, $options);
    }

    public static function getIndexName(): string
    {
        return 'mozjpeg';
    }

    public function process(BinaryInterface $binary, array $options): BinaryInterface
    {
        $type = \strtolower($binary->getMimeType());
        if (!\in_array($type, ['image/jpeg', 'image/jpg'])) {
            return $binary;
        }

        $command = [$this->bin, '-quant-table', 2, '-optimise'];

        $options = \array_replace_recursive($this->options, $options);
        $command[] = '-quality';
        $command[] = $options['quality'];

        // Favor stdin/stdout so we don't waste time creating a new file.
        $process = new Process($command, null, null, null, $options['timeout']);
        $process->setInput($binary->getContent());

        $process->run();

        if (\str_contains($process->getOutput(), 'ERROR') || 0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        return new Binary($process->getOutput(), $binary->getMimeType(), $binary->getFormat());
    }
}
