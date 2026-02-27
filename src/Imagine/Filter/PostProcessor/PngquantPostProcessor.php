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

class PngquantPostProcessor extends AbstractPostProcessor implements PostProcessorInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $options = [
        'quality' => '80-100',
        'timeout' => 30.,
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $bin = '/usr/bin/pngquant',
        array $options = [])
    {
        /** @var array<string, mixed> $merged */
        $merged = \array_replace_recursive($this->options, $options);
        $this->options = $merged;
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
        if (!\in_array($type, ['image/png'], true)) {
            return $binary;
        }

        $command = [$this->bin];
        /** @var array<string, mixed> $options */
        $options = \array_replace_recursive($this->options, $options);

        /** @var string|int $rawQuality */
        $rawQuality = $options['quality'];
        /** @var int|string $rawTimeout */
        $rawTimeout = $options['timeout'];

        $quality = (string) $rawQuality;
        if (!\preg_match('/^\d{1,3}(-\d{1,3})?$/', $quality)) {
            $quality = '80-100';
        }
        $timeout = \max(1, (int) $rawTimeout);

        $command[] = '--quality';
        $command[] = $quality;

        // Read to/from stdout to save resources.
        $command[] = '-';

        $process = new Process($command, null, null, null, (float) $timeout);
        $process->setInput($binary->getContent());
        $process->run();

        $exitCode = $process->getExitCode();

        // 98 and 99 are "quality too low" to compress current image which, while isn't ideal, is not a failure
        if (!\in_array($exitCode, [0, 98, 99], true)) {
            throw new ProcessFailedException($process);
        }

        if (0 !== $exitCode || '' === $process->getOutput()) {
            return $binary;
        }

        return new Binary($process->getOutput(), $binary->getMimeType(), $binary->getFormat());
    }
}
