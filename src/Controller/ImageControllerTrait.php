<?php

declare(strict_types=1);

/*
 * This file is part of the ChamberOrchestra package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChamberOrchestra\ImageBundle\Controller;

use ChamberOrchestra\ImageBundle\Binary\BinaryInterface;
use ChamberOrchestra\ImageBundle\Binary\FileBinaryInterface;
use ChamberOrchestra\ImageBundle\Exception\ExceptionInterface;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ImageControllerTrait
{
    /**
     * Processes an image and returns an appropriate response.
     *
     * @param array<string, mixed> $config
     */
    protected function processAndRespond(
        FilterService $filterService,
        string $path,
        string $filter,
        array $config,
        ?string $redirectUrl = null,
        ?string $resolver = null,
    ): Response {
        try {
            $result = $filterService->processImage($path, $filter, $config, $resolver);

            if ($result->async && null !== $result->binary) {
                return $this->respondWithSourceBinary($result->binary);
            }

            return new RedirectResponse($redirectUrl ?? (string) $result->url, 302);
        } catch (NotLoadableException) {
            return $this->redirectToDefaultOrNotFound($filterService->getDefaultImageUrl($filter), $path);
        } catch (ExceptionInterface $e) {
            throw new HttpException(500, \sprintf('Unable to create image for path "%s" and filter "%s". Reason: %s', $path, $filter, $e->getMessage()), $e);
        }
    }

    /**
     * Redirects to the default image or throws 404.
     *
     * @throws NotFoundHttpException
     */
    protected function redirectToDefaultOrNotFound(?string $defaultUrl, string $path): RedirectResponse
    {
        if (null !== $defaultUrl) {
            return new RedirectResponse($defaultUrl);
        }

        throw new NotFoundHttpException(\sprintf('Source image for path "%s" could not be found', $path));
    }

    protected function respondWithSourceBinary(BinaryInterface $binary): Response
    {
        $headers = ['Content-Type' => $binary->getMimeType(), 'Cache-Control' => 'private, no-store'];

        if ($binary instanceof FileBinaryInterface) {
            $response = new BinaryFileResponse($binary->getPath());
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            $response->headers->add($headers);

            return $response;
        }

        return new Response($binary->getContent(), 200, $headers);
    }
}
