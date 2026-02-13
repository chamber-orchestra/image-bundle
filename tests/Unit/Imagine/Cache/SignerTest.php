<?php

declare(strict_types=1);

namespace Tests\Unit\Imagine\Cache;

use ChamberOrchestra\ImageBundle\Imagine\Cache\Signer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    private Signer $signer;

    protected function setUp(): void
    {
        $this->signer = new Signer('test-secret');
    }

    #[Test]
    public function signReturnsConsistentHash(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg');
        $hash2 = $this->signer->sign('images/photo.jpg');

        self::assertSame($hash1, $hash2);
    }

    #[Test]
    public function signReturnsDifferentHashForDifferentPaths(): void
    {
        $hash1 = $this->signer->sign('images/photo1.jpg');
        $hash2 = $this->signer->sign('images/photo2.jpg');

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function signWithRuntimeConfigReturnsDifferentHash(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg');
        $hash2 = $this->signer->sign('images/photo.jpg', ['fit' => ['width' => 100]]);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function signWithNullRuntimeConfigSameAsWithout(): void
    {
        $hash1 = $this->signer->sign('images/photo.jpg');
        $hash2 = $this->signer->sign('images/photo.jpg', null);

        self::assertSame($hash1, $hash2);
    }

    #[Test]
    public function checkReturnsTrueForValidHash(): void
    {
        $hash = $this->signer->sign('images/photo.jpg');

        self::assertTrue($this->signer->check($hash, 'images/photo.jpg'));
    }

    #[Test]
    public function checkReturnsFalseForInvalidHash(): void
    {
        self::assertFalse($this->signer->check('invalid-hash', 'images/photo.jpg'));
    }

    #[Test]
    public function checkWithRuntimeConfigValidates(): void
    {
        $config = ['fit' => ['width' => 200, 'height' => 150]];
        $hash = $this->signer->sign('images/photo.jpg', $config);

        self::assertTrue($this->signer->check($hash, 'images/photo.jpg', $config));
        self::assertFalse($this->signer->check($hash, 'images/photo.jpg'));
    }

    #[Test]
    public function getSignedPrefixIsConsistent(): void
    {
        $prefix1 = $this->signer->getSignedPrefix('images/photo.jpg');
        $prefix2 = $this->signer->getSignedPrefix('images/photo.jpg');

        self::assertSame($prefix1, $prefix2);
    }

    #[Test]
    public function getSignedPrefixDiffersForDifferentPaths(): void
    {
        $prefix1 = $this->signer->getSignedPrefix('images/photo1.jpg');
        $prefix2 = $this->signer->getSignedPrefix('images/photo2.jpg');

        self::assertNotSame($prefix1, $prefix2);
    }

    #[Test]
    public function signContainsPrefix(): void
    {
        $prefix = $this->signer->getSignedPrefix('images/photo.jpg');
        $sign = $this->signer->sign('images/photo.jpg');

        self::assertStringStartsWith($prefix . '/', $sign);
    }

    #[Test]
    public function differentSecretProducesDifferentHashes(): void
    {
        $signer2 = new Signer('different-secret');

        $hash1 = $this->signer->sign('images/photo.jpg');
        $hash2 = $signer2->sign('images/photo.jpg');

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function signStripsLeadingSlash(): void
    {
        $hash1 = $this->signer->sign('/images/photo.jpg');
        $hash2 = $this->signer->sign('images/photo.jpg');

        self::assertSame($hash1, $hash2);
    }
}
