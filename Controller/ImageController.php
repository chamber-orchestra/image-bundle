<?php

declare(strict_types=1);

namespace ChamberOrchestra\ImageBundle\Controller;

use ChamberOrchestra\ImageBundle\Exception\NonExistingFilterException;
use ChamberOrchestra\ImageBundle\Exception\NotLoadableException;
use ChamberOrchestra\ImageBundle\Exception\RuntimeException;
use ChamberOrchestra\ImageBundle\Imagine\Cache\SignerInterface;
use ChamberOrchestra\ImageBundle\Imagine\Data\DataManager;
use ChamberOrchestra\ImageBundle\Service\FilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends AbstractController
{
    public function __construct(
        private readonly FilterService $filterService,
        private readonly DataManager $dataManager,
        private readonly SignerInterface $signer
    )
    {
    }

    /**
     * This action applies a given filter to a given image, optionally saves the image and outputs it to the browser at the same time.
     *
     * @throws BadRequestHttpException
     * @throws \RuntimeException
     */
    public function filter(Request $request, string $path, string $filter): RedirectResponse
    {
        $path = \urldecode($path);
        $resolver = $request->get('resolver');

        try {
            return new RedirectResponse($this->filterService->getUrlOfFilteredImage($path, $filter, $resolver), 301);
        } catch (NotLoadableException $e) {
            if (null !== $this->dataManager->getDefaultImageUrl($filter)) {
                return new RedirectResponse($this->dataManager->getDefaultImageUrl($filter));
            }

            throw new NotFoundHttpException(\sprintf('Source image for path "%s" could not be found', $path));
        } catch (NonExistingFilterException $e) {
            throw new NotFoundHttpException(\sprintf('Requested non-existing filter "%s"', $filter));
        } catch (RuntimeException $e) {
            throw new \RuntimeException(\sprintf('Unable to create image for path "%s" and filter "%s". Story was "%s"', $path, $filter, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @throws BadRequestHttpException
     * @throws \RuntimeException
     */
    public function filterRuntime(Request $request, string $hash, string $path, string $filter): RedirectResponse
    {
        $resolver = $request->get('resolver');
        $runtimeProcessors = $request->query->all('processors');

        if (true !== $this->signer->check($hash, $path, $runtimeProcessors)) {
            throw new BadRequestHttpException(\sprintf('Signed url does not pass the sign check for path "%s" and filter "%s" and runtime config %s', $path, $filter, \json_encode($runtimeProcessors)));
        }

        try {
            return new RedirectResponse($this->filterService->getRuntimeProcessedImageUrl($path, $filter, $runtimeProcessors, $resolver), 301);
        } catch (NotLoadableException $e) {
            if (null !== $this->dataManager->getDefaultImageUrl($filter)) {
                return new RedirectResponse($this->dataManager->getDefaultImageUrl($filter));
            }

            throw new NotFoundHttpException(\sprintf('Source image for path "%s" could not be found', $path));
        } catch (NonExistingFilterException $e) {
            throw new NotFoundHttpException(\sprintf('Requested non-existing filter "%s"', $filter));
        } catch (RuntimeException $e) {
            throw new \RuntimeException(\sprintf('Unable to create image for path "%s" and filter "%s". Story was "%s"', $hash.'/'.$path, $filter, $e->getMessage()), 0, $e);
        }
    }
}
