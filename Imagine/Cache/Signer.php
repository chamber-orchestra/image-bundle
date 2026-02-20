<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

readonly class Signer implements SignerInterface
{
    public function __construct(private string $secret)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function sign(string $path, ?array $runtimeConfig = null): string
    {
        $name = 'default';
        if ($runtimeConfig) {
            \array_walk_recursive($runtimeConfig, function (&$value): void {
                $value = null !== $value ? (string) $value : '';
            });

            \ksort($runtimeConfig);
            $name = \json_encode($runtimeConfig, \JSON_THROW_ON_ERROR);
        }

        return $this->getSignedPrefix($path).'/'.$this->hash($name);
    }

    public function getSignedPrefix(string $path): string
    {
        return $this->hash($path);
    }

    /**
     * {@inheritdoc}
     */
    public function check(string $hash, string $path, ?array $runtimeConfig = null): bool
    {
        return $hash === $this->sign($path, $runtimeConfig);
    }

    private function hash(string $string): string
    {
        return \substr(\preg_replace('/[^a-zA-Z0-9-_]/', '', \base64_encode(\hash_hmac('sha256', \ltrim($string, '/'), $this->secret, true))), 0, 16);
    }
}
