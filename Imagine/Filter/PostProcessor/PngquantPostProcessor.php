<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Filter\PostProcessor;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Model\Binary;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PngquantPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    private array $options = [
        'quality' => '80-100',
        'timeout' => 30.,
    ];

    public function __construct(
        private readonly string $bin = '/usr/bin/pngquant',
        array $options = [])
    {
        $this->options = \array_replace_recursive($this->options, $options);
    }

    public function process(BinaryInterface $binary, array $options): BinaryInterface
    {
        $type = \strtolower($binary->getMimeType());
        if (!\in_array($type, ['image/png'])) {
            return $binary;
        }

        $command = [$this->bin];
        $options = \array_replace_recursive($this->options, $options);
        $command[] = '--quality';
        $command[] = $options['quality'];

        // Read to/from stdout to save resources.
        $command[] = '-';

        $process = new Process($command, null, null, null, $options['timeout']);
        $process->setInput($binary->getContent());
        $process->run();

        // 98 and 99 are "quality too low" to compress current image which, while isn't ideal, is not a failure
        if (!\in_array($process->getExitCode(), [0, 98, 99])) {
            throw new ProcessFailedException($process);
        }

        return new Binary($process->getOutput(), $binary->getMimeType(), $binary->getFormat());
    }
}
