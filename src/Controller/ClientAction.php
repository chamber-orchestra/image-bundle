<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Controller;

use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;
use ChamberOrchestra\ImageBundle\Imagine\Cache\CacheManager;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClientAction
{
    use ImageControllerTrait;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly FilterConfiguration $filterConfig,
        private readonly CacheManager $cacheManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->verifyRequest($request);

        $path = \ltrim(\urldecode($request->query->get('path', '')), '/');
        $filter = $request->query->get('filter', '');
        $type = $request->query->get('type', '');

        $config = $this->buildConfig($request, $type);
        $relativePath = $this->cacheManager->getPath($path, $config, $filter);
        $this->verifyPath($relativePath, $request);

        if ($this->cacheManager->isStored($relativePath, $filter)) {
            return new RedirectResponse($this->cacheManager->resolve($relativePath, $filter), 301);
        }

        return $this->processAndRespond(
            $this->filterService,
            $path,
            $filter,
            $config,
        );
    }

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    private function verifyRequest(Request $request): void
    {
        $path = $request->query->get('path', '');
        $filter = $request->query->get('filter', '');
        $type = $request->query->get('type', '');

        if ('' === $path || '' === $filter || '' === $type) {
            throw new BadRequestHttpException('Missing required query parameters "path", "filter" and "type".');
        }

        try {
            $filterConf = $this->filterConfig->get($filter);
        } catch (NonExistingFilterException) {
            throw new NotFoundHttpException(\sprintf('Requested non-existing filter "%s"', $filter));
        }

        if (empty($filterConf['exposed'])) {
            throw new BadRequestHttpException(\sprintf('Filter "%s" is not exposed.', $filter));
        }
    }

    /**
     * @throws BadRequestHttpException
     */
    private function verifyPath(string $expectedPath, Request $request): void
    {
        /** @var string $pathHash */
        $pathHash = $request->attributes->get('pathHash');
        /** @var string $optionsHash */
        $optionsHash = $request->attributes->get('optionsHash');
        /** @var string $name */
        $name = $request->attributes->get('name');
        /** @var string $format */
        $format = $request->attributes->get('format');

        $actualPath = \sprintf('%s/%s/%s@%dx.%s', $pathHash, $optionsHash, $name, $request->attributes->getInt('density'), $format);

        if (!\hash_equals($expectedPath, $actualPath)) {
            throw new BadRequestHttpException('Signed URL does not match expected path.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(Request $request, string $type): array
    {
        /** @var string $format */
        $format = $request->attributes->get('format');

        return [
            $type => [
                'width' => $request->query->getInt('width'),
                'height' => $request->query->getInt('height'),
                'density' => $request->attributes->getInt('density'),
            ],
            'output' => [
                'quality' => $request->query->getInt('quality'),
                'format' => \mb_strtolower($format),
            ],
        ];
    }
}
