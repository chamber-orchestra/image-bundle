<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Imagine\Cache\Resolver;

use Aws\S3\S3Client;
use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;

class S3Resolver implements ResolverInterface
{
    private string $cachePrefix;
    private ?string $uriPrefix;

    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        ?string $uriPrefix = null,
        string $cachePrefix = 'media',
        private readonly string $cacheControl = 'public, max-age=31536000',
        private readonly ?string $acl = null,
    ) {
        $this->cachePrefix = \trim($cachePrefix, '/');
        $this->uriPrefix = null !== $uriPrefix ? \rtrim($uriPrefix, '/') : null;
    }

    #[\Override]
    public function isStored(string $path, string $filter): bool
    {
        return $this->client->doesObjectExist($this->bucket, $this->getObjectKey($path, $filter));
    }

    #[\Override]
    public function resolve(string $path, string $filter): string
    {
        $key = $this->getObjectKey($path, $filter);

        if (null !== $this->uriPrefix) {
            return $this->uriPrefix.'/'.$key;
        }

        return $this->client->getObjectUrl($this->bucket, $key);
    }

    #[\Override]
    public function store(BinaryInterface $binary, string $path, string $filter): void
    {
        $params = [
            'Bucket' => $this->bucket,
            'Key' => $this->getObjectKey($path, $filter),
            'Body' => $binary->getContent(),
            'ContentType' => $binary->getMimeType(),
            'CacheControl' => $this->cacheControl,
        ];

        if (null !== $this->acl) {
            $params['ACL'] = $this->acl;
        }

        $this->client->putObject($params);
    }

    #[\Override]
    public function remove(string $prefix): void
    {
        $keyPrefix = \implode('/', \array_filter([$this->cachePrefix, $prefix])).'/';
        $continuationToken = null;

        do {
            $params = ['Bucket' => $this->bucket, 'Prefix' => $keyPrefix];
            if (null !== $continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            /** @var array{Contents?: list<array{Key: string}>, IsTruncated?: bool, NextContinuationToken?: string} $result */
            $result = $this->client->listObjectsV2($params);

            $objects = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $objects[] = ['Key' => $object['Key']];
            }

            if ([] !== $objects) {
                $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Delete' => ['Objects' => $objects],
                ]);
            }

            $continuationToken = !empty($result['IsTruncated']) ? ($result['NextContinuationToken'] ?? null) : null;
        } while (null !== $continuationToken);
    }

    #[\Override]
    public function resolveIfStored(string $path, string $filter): ?string
    {
        $key = $this->getObjectKey($path, $filter);

        if (!$this->client->doesObjectExist($this->bucket, $key)) {
            return null;
        }

        if (null !== $this->uriPrefix) {
            return $this->uriPrefix.'/'.$key;
        }

        return $this->client->getObjectUrl($this->bucket, $key);
    }

    private const array DANGEROUS_EXTENSIONS = ['php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'htaccess'];

    private function getObjectKey(string $path, string $filter): string
    {
        $path = \str_replace('://', '---', $path);

        $ext = \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));
        if (\in_array($ext, self::DANGEROUS_EXTENSIONS, true)) {
            $path = \substr($path, 0, -\strlen($ext)).'bin';
        }

        $segments = \array_filter([$this->cachePrefix, \ltrim($path, '/')]);

        return \implode('/', $segments);
    }
}
