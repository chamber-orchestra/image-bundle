<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache;

readonly class Signer implements SignerInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function sign(string $path, string $secret, array $config): string
    {
        return $this->signWith($path, $secret, $config);
    }

    public function getSignedPrefix(string $path, string $secret): string
    {
        return $this->hash($path, $secret);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function check(string $hash, string $path, string $secret, array $config): bool
    {
        return \hash_equals($this->signWith($path, $secret, $config), $hash);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function signWith(string $path, string $secret, array $config): string
    {
        $name = 'default';
        if ($config) {
            \array_walk_recursive($config, static function (mixed &$value): void {
                $value = \is_scalar($value) ? (string) $value : '';
            });

            $this->recursiveKsort($config);
            $name = \json_encode($config, \JSON_THROW_ON_ERROR);
        }

        return $this->hash($path, $secret).'/'.$this->hash($name, $secret);
    }

    private function hash(string $string, string $secret): string
    {
        $raw = \hash_hmac('sha256', \ltrim($string, '/'), $secret, true);

        // base64url encoding (RFC 4648 §5) — no character stripping needed
        return \substr(\rtrim(\strtr(\base64_encode($raw), '+/', '-_'), '='), 0, 16);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private function recursiveKsort(array &$array): void
    {
        \ksort($array);
        foreach ($array as &$value) {
            if (\is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }
}
