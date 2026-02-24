<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Imagine\Cache\Resolver;

use Aws\S3\S3Client;
use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver\S3Resolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class S3ResolverTest extends TestCase
{
    private S3Client $client;

    /** @var list<array{method: string, args: list<mixed>}> */
    private array $s3Calls = [];

    protected function setUp(): void
    {
        $this->s3Calls = [];
        $this->client = $this->createMock(S3Client::class);
        $this->client->method('__call')
            ->willReturnCallback(function (string $name, array $args): \Aws\Result {
                $this->s3Calls[] = ['method' => $name, 'args' => $args];

                return new \Aws\Result([]);
            });
    }

    #[Test]
    public function isStoredReturnsTrueWhenObjectExists(): void
    {
        $this->client->expects($this->once())->method('doesObjectExist')
            ->with('rehearsal-recordings', 'media/scores/moonlight_sonata.jpg')
            ->willReturn(true);

        $resolver = $this->makeResolver();

        self::assertTrue($resolver->isStored('scores/moonlight_sonata.jpg', 'thumbnail'));
    }

    #[Test]
    public function isStoredReturnsFalseWhenObjectDoesNotExist(): void
    {
        $this->client->expects($this->once())->method('doesObjectExist')
            ->with('rehearsal-recordings', 'media/scores/missing_sonata.jpg')
            ->willReturn(false);

        $resolver = $this->makeResolver();

        self::assertFalse($resolver->isStored('scores/missing_sonata.jpg', 'thumbnail'));
    }

    #[Test]
    public function resolveReturnsUrlWithUriPrefixWhenConfigured(): void
    {
        $resolver = new S3Resolver(
            $this->client,
            'rehearsal-recordings',
            'https://cdn.orchestra.example.com',
            'media',
        );

        $url = $resolver->resolve('scores/violin_concerto.jpg', 'thumbnail');

        self::assertSame('https://cdn.orchestra.example.com/media/scores/violin_concerto.jpg', $url);
    }

    #[Test]
    public function resolveTrimsTrailingSlashFromUriPrefix(): void
    {
        $resolver = new S3Resolver(
            $this->client,
            'rehearsal-recordings',
            'https://cdn.orchestra.example.com/',
            'media',
        );

        $url = $resolver->resolve('scores/photo.jpg', 'thumb');

        self::assertSame('https://cdn.orchestra.example.com/media/scores/photo.jpg', $url);
    }

    #[Test]
    public function resolveUsesS3ClientUrlWhenNoUriPrefix(): void
    {
        $this->client->expects($this->once())->method('getObjectUrl')
            ->with('rehearsal-recordings', 'media/scores/symphony_no_5.jpg')
            ->willReturn('https://rehearsal-recordings.s3.amazonaws.com/media/scores/symphony_no_5.jpg');

        $resolver = $this->makeResolver();

        $url = $resolver->resolve('scores/symphony_no_5.jpg', 'thumbnail');

        self::assertSame('https://rehearsal-recordings.s3.amazonaws.com/media/scores/symphony_no_5.jpg', $url);
    }

    #[Test]
    public function storeUploadsObjectToS3(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getContent')->willReturn('processed-image-data');
        $binary->method('getMimeType')->willReturn('image/webp');

        $resolver = $this->makeResolver();
        $resolver->store($binary, 'scores/moonlight_sonata.webp', 'thumbnail');

        self::assertCount(1, $this->s3Calls);
        self::assertSame('putObject', $this->s3Calls[0]['method']);
        self::assertSame([
            'Bucket' => 'rehearsal-recordings',
            'Key' => 'media/scores/moonlight_sonata.webp',
            'Body' => 'processed-image-data',
            'ContentType' => 'image/webp',
            'CacheControl' => 'public, max-age=31536000',
        ], $this->s3Calls[0]['args'][0]);
    }

    #[Test]
    public function storeIncludesAclWhenConfigured(): void
    {
        $binary = $this->createMock(BinaryInterface::class);
        $binary->method('getContent')->willReturn('processed-image-data');
        $binary->method('getMimeType')->willReturn('image/jpeg');

        $resolver = new S3Resolver(
            $this->client,
            'rehearsal-recordings',
            null,
            'media',
            'public, max-age=86400',
            'public-read',
        );
        $resolver->store($binary, 'scores/waltz.jpg', 'thumbnail');

        self::assertCount(1, $this->s3Calls);
        self::assertSame([
            'Bucket' => 'rehearsal-recordings',
            'Key' => 'media/scores/waltz.jpg',
            'Body' => 'processed-image-data',
            'ContentType' => 'image/jpeg',
            'CacheControl' => 'public, max-age=86400',
            'ACL' => 'public-read',
        ], $this->s3Calls[0]['args'][0]);
    }

    #[Test]
    public function dangerousExtensionsAreReplacedInObjectKey(): void
    {
        $this->client->expects($this->once())->method('doesObjectExist')
            ->with('rehearsal-recordings', 'media/scores/evil.bin')
            ->willReturn(false);

        $resolver = $this->makeResolver();

        self::assertFalse($resolver->isStored('scores/evil.php', 'thumbnail'));
    }

    #[Test]
    public function protocolInPathIsReplacedInObjectKey(): void
    {
        $resolver = new S3Resolver(
            $this->client,
            'rehearsal-recordings',
            'https://cdn.orchestra.example.com',
            'media',
        );

        $url = $resolver->resolve('http://example.com/scores/photo.jpg', 'thumb');

        // The object key should use '---' instead of '://', but CDN prefix keeps its '://'
        self::assertStringContainsString('http---example.com', $url);
        self::assertStringNotContainsString('http://example.com', $url);
    }

    #[Test]
    public function removeListsAndDeletesAllObjectsUnderPrefix(): void
    {
        $calls = [];
        $client = $this->createMock(S3Client::class);
        $client->method('__call')
            ->willReturnCallback(function (string $name, array $args) use (&$calls): \Aws\Result {
                $calls[] = ['method' => $name, 'args' => $args];

                if ('listObjectsV2' === $name) {
                    return new \Aws\Result(['Contents' => [
                        ['Key' => 'media/Ab3xK9_z/Qm7pLw2d/sonata@1x.webp'],
                        ['Key' => 'media/Ab3xK9_z/Xk9mNp3q/sonata@2x.avif'],
                    ]]);
                }

                return new \Aws\Result([]);
            });

        $resolver = new S3Resolver($client, 'rehearsal-recordings');
        $resolver->remove('Ab3xK9_z');

        self::assertCount(2, $calls);
        self::assertSame('listObjectsV2', $calls[0]['method']);
        self::assertSame([
            'Bucket' => 'rehearsal-recordings',
            'Prefix' => 'media/Ab3xK9_z/',
        ], $calls[0]['args'][0]);
        self::assertSame('deleteObjects', $calls[1]['method']);
        self::assertSame([
            'Bucket' => 'rehearsal-recordings',
            'Delete' => ['Objects' => [
                ['Key' => 'media/Ab3xK9_z/Qm7pLw2d/sonata@1x.webp'],
                ['Key' => 'media/Ab3xK9_z/Xk9mNp3q/sonata@2x.avif'],
            ]],
        ], $calls[1]['args'][0]);
    }

    #[Test]
    public function removeSkipsBatchDeleteWhenNoObjectsFound(): void
    {
        $resolver = $this->makeResolver();
        $resolver->remove('empty_prefix');

        // Only listObjectsV2, no deleteObjects
        self::assertCount(1, $this->s3Calls);
        self::assertSame('listObjectsV2', $this->s3Calls[0]['method']);
    }

    #[Test]
    public function resolveStripsLeadingSlashFromPath(): void
    {
        $this->client->expects($this->once())->method('doesObjectExist')
            ->with('rehearsal-recordings', 'media/scores/photo.jpg')
            ->willReturn(true);

        $resolver = $this->makeResolver();

        self::assertTrue($resolver->isStored('/scores/photo.jpg', 'thumbnail'));
    }

    #[Test]
    public function customCachePrefixIsUsedInObjectKey(): void
    {
        $resolver = new S3Resolver(
            $this->client,
            'rehearsal-recordings',
            'https://cdn.orchestra.example.com',
            'images/cache',
        );

        $url = $resolver->resolve('scores/photo.jpg', 'thumb');

        self::assertSame('https://cdn.orchestra.example.com/images/cache/scores/photo.jpg', $url);
    }

    private function makeResolver(): S3Resolver
    {
        return new S3Resolver($this->client, 'rehearsal-recordings', null, 'media');
    }
}
