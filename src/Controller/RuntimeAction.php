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
use ChamberOrchestra\ImageBundle\Imagine\Cache\SignerInterface;
use ChamberOrchestra\ImageBundle\Imagine\Filter\FilterConfiguration;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RuntimeAction
{
    use ImageControllerTrait;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly FilterConfiguration $filterConfig,
        private readonly SignerInterface $signer,
    ) {
    }

    /**
     * @throws BadRequestHttpException
     * @throws \RuntimeException
     */
    public function __invoke(Request $request, string $hash, string $path, string $filter): Response
    {
        $path = \urldecode($path);

        try {
            $this->filterConfig->get($filter);
        } catch (NonExistingFilterException) {
            throw new NotFoundHttpException(\sprintf('Requested non-existing filter "%s"', $filter));
        }

        $resolver = $request->query->get('resolver');
        /** @var array<string, mixed> $processors */
        $processors = $request->query->all('processors');
        /** @var array<string, mixed> $output */
        $output = $request->query->all('output');

        if (!$processors) {
            throw new BadRequestHttpException(\sprintf('Runtime processors must be provided for path "%s" and filter "%s"', $path, $filter));
        }

        if (self::arrayDepth($processors) > 5) {
            throw new BadRequestHttpException('Runtime processors configuration is too deeply nested.');
        }

        $filterSecret = $this->filterConfig->get($filter)['secret'];
        $signingConfig = $output ? ['processors' => $processors, 'output' => $output] : $processors;

        if (true !== $this->signer->check($hash, $path, $filterSecret, $signingConfig)) {
            throw new BadRequestHttpException(\sprintf('Signed url does not pass the sign check for path "%s" and filter "%s" and runtime config %s', $path, $filter, \json_encode($signingConfig)));
        }

        $fullConfig = ['processors' => $processors];
        if ($output) {
            $fullConfig['output'] = $output;
        }

        return $this->processAndRespond($this->filterService, $path, $filter, $fullConfig, resolver: $resolver);
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private static function arrayDepth(array $array, int $depth = 1): int
    {
        $maxDepth = $depth;
        foreach ($array as $value) {
            if (\is_array($value)) {
                $maxDepth = \max($maxDepth, self::arrayDepth($value, $depth + 1));
                if ($maxDepth > 5) {
                    return $maxDepth;
                }
            }
        }

        return $maxDepth;
    }
}
