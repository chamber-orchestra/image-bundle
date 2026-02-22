<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    private Signer $signer;

    protected function setUp(): void
    {
        $this->signer = new Signer();
    }

    #[Test]
    public function signReturnsConsistentHash(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg', 'test-secret', []);
        $hash2 = $this->signer->sign('images/photo.jpg', 'test-secret', []);

        self::assertSame($hash1, $hash2);
    }

    #[Test]
    public function signReturnsDifferentHashForDifferentPaths(): void
    {
        $hash1 = $this->signer->sign('images/photo1.jpg', 'test-secret', []);
        $hash2 = $this->signer->sign('images/photo2.jpg', 'test-secret', []);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function signWithRuntimeConfigReturnsDifferentHash(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg', 'test-secret', []);
        $hash2 = $this->signer->sign('images/photo.jpg', 'test-secret', ['fit' => ['width' => 100]]);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function checkReturnsTrueForValidHash(): void
    {
        $hash = $this->signer->sign('images/photo.jpg', 'test-secret', []);

        self::assertTrue($this->signer->check($hash, 'images/photo.jpg', 'test-secret', []));
    }

    #[Test]
    public function checkReturnsFalseForInvalidHash(): void
    {
        self::assertFalse($this->signer->check('invalid-hash', 'images/photo.jpg', 'test-secret', []));
    }

    #[Test]
    public function checkWithRuntimeConfigValidates(): void
    {
        $config = ['fit' => ['width' => 200, 'height' => 150]];
        $hash = $this->signer->sign('images/photo.jpg', 'test-secret', $config);

        self::assertTrue($this->signer->check($hash, 'images/photo.jpg', 'test-secret', $config));
        self::assertFalse($this->signer->check($hash, 'images/photo.jpg', 'test-secret', []));
    }

    #[Test]
    public function getSignedPrefixIsConsistent(): void
    {
        $prefix1 = $this->signer->getSignedPrefix('images/photo.jpg', 'test-secret');
        $prefix2 = $this->signer->getSignedPrefix('images/photo.jpg', 'test-secret');

        self::assertSame($prefix1, $prefix2);
    }

    #[Test]
    public function getSignedPrefixDiffersForDifferentPaths(): void
    {
        $prefix1 = $this->signer->getSignedPrefix('images/photo1.jpg', 'test-secret');
        $prefix2 = $this->signer->getSignedPrefix('images/photo2.jpg', 'test-secret');

        self::assertNotSame($prefix1, $prefix2);
    }

    #[Test]
    public function signContainsPrefix(): void
    {
        $prefix = $this->signer->getSignedPrefix('images/photo.jpg', 'test-secret');
        $sign = $this->signer->sign('images/photo.jpg', 'test-secret', []);

        self::assertStringStartsWith($prefix.'/', $sign);
    }

    #[Test]
    public function differentSecretProducesDifferentHashes(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg', 'test-secret', []);
        $hash2 = $this->signer->sign('images/photo.jpg', 'different-secret', []);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function signStripsLeadingSlash(): void
    {
        $hash1 = $this->signer->sign('/images/photo.jpg', 'test-secret', []);
        $hash2 = $this->signer->sign('images/photo.jpg', 'test-secret', []);

        self::assertSame($hash1, $hash2);
    }

    #[Test]
    public function differentSecretProducesDifferentPrefixes(): void
    {
        $prefix1 = $this->signer->getSignedPrefix('images/photo.jpg', 'test-secret');
        $prefix2 = $this->signer->getSignedPrefix('images/photo.jpg', 'other-secret');

        self::assertNotSame($prefix1, $prefix2);
    }

    #[Test]
    public function hashSegmentsAre16CharactersLong(): void
    {
        $hash = $this->signer->sign('images/photo.jpg', 'test-secret', []);
        [$pathHash, $optionsHash] = \explode('/', $hash);

        self::assertSame(16, \strlen($pathHash));
        self::assertSame(16, \strlen($optionsHash));
    }

    #[Test]
    public function hashUsesBase64urlCharactersOnly(): void
    {
        $hash = $this->signer->sign('images/photo.jpg', 'test-secret', ['fit' => ['width' => 100]]);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16}\/[A-Za-z0-9_-]{16}$/', $hash);
    }

    #[Test]
    public function prefixIs16CharactersLong(): void
    {
        $prefix = $this->signer->getSignedPrefix('images/photo.jpg', 'test-secret');

        self::assertSame(16, \strlen($prefix));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16}$/', $prefix);
    }
}
