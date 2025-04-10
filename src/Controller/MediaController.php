<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Image;
use App\ImageLibraryGuesser;
use App\Repository\ImageRepository;
use App\Security\AppUserService;
use DateTime;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageOptimizer;
use Phyxo\Image\ImageRect;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SizingParams;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Routing\Attribute\Route;

class MediaController extends AbstractController
{
    protected ImageStandardParams $image_std_params;
    private bool $forAdmin = false;

    #[Route(
        '/media/{path}-{derivative}.{image_extension}',
        name: 'media',
        requirements: ['derivative' => 'sq|th|2s|xs|sm|me|la|xl|xx', 'path' => '.*', 'image_extension' => 'jpg|jpeg|png|mp4',
        ]
    )]
    public function derivative(
        Request $request,
        string $path,
        string $derivative,
        string $image_extension,
        string $mediaCacheDir,
        ImageStandardParams $image_std_params,
        ImageRepository $imageRepository,
        string $rootProjectDir,
        MimeTypeGuesserInterface $mimeTypeGuesser,
        ImageLibraryGuesser $imageLibraryGuesser,
        AppUserService $appUserService,
    ): Response {
        $image_path = sprintf('%s.%s', $path, $image_extension);
        $derivative_path = sprintf('%s-%s.%s', $path, $derivative, $image_extension);

        $image_src = sprintf('%s/%s', $rootProjectDir, $image_path);
        $derivative_src = sprintf('%s/%s', $mediaCacheDir, $derivative_path);
        $derivative_params = $image_std_params->getParamsFromDerivative($derivative);

        // deal with ./ at the beginning of path
        $image = $imageRepository->findOneByUnsanePath($path . '.' . $image_extension);
        if (is_null($image)) {
            return new Response('Db file path not found ', Response::HTTP_NOT_FOUND);
        }

        if (!$this->forAdmin && !$imageRepository->isAuthorizedToUser($image->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            return new Response('User not allowed to see that image ', Response::HTTP_FORBIDDEN);
        }

        if (!is_readable($image_src)) {
            return new Response('Image not found ' . $image_src, Response::HTTP_NOT_FOUND);
        }

        if (!$this->needGenerate($image_src, $derivative_src)) {
            $response = $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
            $response->isNotModified($request);

            return $response;
        }

        $imageOptimizer = new ImageOptimizer($image_src, $imageLibraryGuesser->getLibrary());
        $this->updateImageRotateIfNeeded($image, $imageOptimizer, $imageRepository);
        $imageOptimizer->rotate($imageOptimizer->getRotationAngle());
        $this->cropAndScale($image, $image_std_params, $derivative_params, $imageOptimizer);
        $imageOptimizer->strip();

        $this->cacheDerivative($mediaCacheDir . '/' . $derivative_path, $imageOptimizer, $image_std_params->getQuality());

        return $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
    }

    #[Route(
        '/media/{path}-cu_{sizes}.{image_extension}',
        name: 'media_custom',
        defaults: ['derivative' => 'cu'],
        requirements: [
            'path' => '.*', 'image_extension' => 'jpg|jpeg|png|mp4', 'sizes' => '(e|s)[^\.]*',
        ]
    )]
    public function custom(
        Request $request,
        string $path,
        string $derivative,
        string $sizes,
        string $image_extension,
        string $mediaCacheDir,
        ImageStandardParams $image_std_params,
        ImageRepository $imageRepository,
        string $rootProjectDir,
        MimeTypeGuesserInterface $mimeTypeGuesser,
        ImageLibraryGuesser $imageLibraryGuesser,
        AppUserService $appUserService,
    ): Response {
        $image_path = sprintf('%s.%s', $path, $image_extension);
        $derivative_path = sprintf('%s-%s%s.%s', $path, $derivative, $sizes, $image_extension);

        $image_src = sprintf('%s/%s', $rootProjectDir, $image_path);
        $derivative_src = sprintf('%s/%s', $mediaCacheDir, $derivative_path);
        $derivative_params = $this->parseCustomSizes($sizes);

        // deal with ./ at the beginning of path
        $image = $imageRepository->findOneByUnsanePath($path . '.' . $image_extension);
        if (is_null($image)) {
            return new Response('Db file path not found ', Response::HTTP_NOT_FOUND);
        }

        if (!$imageRepository->isAuthorizedToUser($image->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            return new Response('User not allowed to see that image ', Response::HTTP_FORBIDDEN);
        }

        $image_std_params->applyWatermark($derivative_params);

        if ($derivative_params->getSizing()->getIdealSize()[0] < 20 || $derivative_params->getSizing()->getIdealSize()[1] < 20) {
            return new Response('Invalid size', Response::HTTP_BAD_REQUEST);
        }

        if ($derivative_params->getSizing()->getMaxCrop() < 0 || $derivative_params->getSizing()->getMaxCrop() > 1) {
            return new Response('Invalid crop', Response::HTTP_BAD_REQUEST);
        }

        $key = [];
        $derivative_params->addUrlTokens($key);
        $key = implode('_', $key);

        if (!$image_std_params->hasCustom($key)) {
            return new Response('Size not allowed', Response::HTTP_FORBIDDEN);
        }

        if (!is_readable($image_src)) {
            return new Response('Image not found ' . $image_src, Response::HTTP_NOT_FOUND);
        }

        if (!$this->needGenerate($image_src, $derivative_src)) {
            $response = $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
            $response->isNotModified($request);

            return $response;
        }

        $imageOptimizer = new ImageOptimizer($image_src, $imageLibraryGuesser->getLibrary());
        $this->updateImageRotateIfNeeded($image, $imageOptimizer, $imageRepository);
        $imageOptimizer->rotate($imageOptimizer->getRotationAngle());
        $this->cropAndScale($image, $image_std_params, $derivative_params, $imageOptimizer);
        $imageOptimizer->strip();

        $this->cacheDerivative($mediaCacheDir . '/' . $derivative_path, $imageOptimizer, $image_std_params->getQuality());

        return $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
    }

    #[Route(
        '/media/{path}.{image_extension}',
        name: 'media_original',
        requirements: [
            'path' => '[^_]*', 'image_extension' => 'jpg|jpeg|png',
        ]
    )]
    public function original(
        Request $request,
        string $path,
        string $image_extension,
        string $mediaCacheDir,
        ImageStandardParams $image_std_params,
        ImageRepository $imageRepository,
        string $rootProjectDir,
        MimeTypeGuesserInterface $mimeTypeGuesser,
        ImageLibraryGuesser $imageLibraryGuesser,
        AppUserService $appUserService,
    ): Response {
        $image_path = sprintf('%s.%s', $path, $image_extension);
        $derivative_path = sprintf('%s-%s.%s', $path, 'original', $image_extension);
        $image_src = sprintf('%s/%s', $rootProjectDir, $image_path);
        $derivative_src = sprintf('%s/%s', $mediaCacheDir, $derivative_path);

        // deal with ./ at the beginning of path
        $image = $imageRepository->findOneByUnsanePath($path . '.' . $image_extension);
        if (is_null($image)) {
            return new Response('Db file path not found ', Response::HTTP_NOT_FOUND);
        }

        if (!$imageRepository->isAuthorizedToUser($image->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            return new Response('User not allowed to see that image ', Response::HTTP_FORBIDDEN);
        }

        $original_size = [$image->getWidth(), $image->getHeight()];
        $derivative_params = new DerivativeParams(new SizingParams($original_size));

        if (!is_readable($image_src)) {
            return new Response('Image not found ' . $image_src, Response::HTTP_NOT_FOUND);
        }

        if (!$this->needGenerate($image_src, $derivative_src)) {
            $response = $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
            $response->isNotModified($request);

            return $response;
        }

        $imageOptimizer = new ImageOptimizer($image_src, $imageLibraryGuesser->getLibrary());
        $this->updateImageRotateIfNeeded($image, $imageOptimizer, $imageRepository);
        $imageOptimizer->rotate($imageOptimizer->getRotationAngle());
        $this->cropAndScale($image, $image_std_params, $derivative_params, $imageOptimizer);
        $imageOptimizer->strip();

        $this->cacheDerivative($mediaCacheDir . '/' . $derivative_path, $imageOptimizer, $image_std_params->getQuality());

        return $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path, $mimeTypeGuesser);
    }

    #[Route(
        '/admin/media/{path}-{derivative}.{image_extension}',
        name: 'admin_media',
        requirements: [
            'path' => '[^_]*', 'derivative' => 'sq|th|2s|xs|sm|me|la|xl|xx', 'image_extension' => 'jpg|jpeg|png',
        ]
    )]
    public function mediaForAdmin(string $path, string $derivative, string $image_extension): Response
    {
        $this->forAdmin = true;

        return $this->forward(
            MediaController::class . '::derivative',
            ['path' => $path, 'derivative' => $derivative, 'image_extension' => $image_extension]
        );
    }

    protected function needGenerate(string $image_src, string $derivative_src): bool
    {
        $src_mtime = filemtime($image_src);
        $need_generate = true;

        if (is_readable($derivative_src)) {
            $derivative_mtime = filemtime($derivative_src);
            $need_generate = $derivative_mtime < $src_mtime;
        }

        return $need_generate;
    }

    protected function updateImageRotateIfNeeded(Image $image, ImageOptimizer $imageOptimizer, ImageRepository $imageRepository): void
    {
        if ($image->getRotation() === 0 || is_null($image->getRotation())) {
            $image->setRotation($imageOptimizer->getRotationCode());
            $imageRepository->addOrUpdateImage($image);
        }
    }

    protected function cacheDerivative(string $destination_path, ImageOptimizer $imageOptimizer, int $quality): void
    {
        $fs = new Filesystem();
        $fs->mkdir(dirname($destination_path));

        $imageOptimizer->setCompressionQuality($quality);
        $imageOptimizer->write($destination_path);
        chmod($destination_path, 0644);
    }

    protected function cropAndScale(Image $image, ImageStandardParams $image_std_params, DerivativeParams $derivative_params, ImageOptimizer $imageOptimizer): void
    {
        $coi = (string) $image->getCoi();
        $crop_rect = null;
        $scaled_size = [];
        $o_size = [$imageOptimizer->getWidth(), $imageOptimizer->getHeight()];
        $d_size = [$imageOptimizer->getWidth(), $imageOptimizer->getHeight()];
        $derivative_params->getSizing()->compute($o_size, $crop_rect, $scaled_size, $coi);
        if ($crop_rect instanceof ImageRect) {
            $imageOptimizer->crop($crop_rect->width(), $crop_rect->height(), $crop_rect->getLeft(), $crop_rect->getTop());
        }

        if ($scaled_size !== []) {
            $imageOptimizer->resize($scaled_size[0], $scaled_size[1]);
            $d_size = $scaled_size;
        }

        if ($derivative_params->willWatermark($d_size, $image_std_params)) {
            $imageOptimizer->addWatermak($image_std_params->getWatermark());
        }
    }

    /**
     * @return array<int, int>
     */
    private function urlToSize(string $s): array
    {
        $pos = strpos($s, 'x');
        if ($pos === false) {
            return [(int) $s, (int) $s];
        }

        return [(int) substr($s, 0, $pos), (int) substr($s, $pos + 1)];
    }

    private function parseCustomSizes(string $sizes): DerivativeParams
    {
        $size = $this->urlToSize(substr($sizes, 1));
        if ($sizes[0] === 's') {
            $crop = 0;
            $min_size = '';
        } else { // e
            $crop = 1;
            $min_size = $size;
        }

        return new DerivativeParams(new SizingParams($size, $crop, $min_size));
    }

    protected function makeDerivativeResponse(string $image_path, MimeTypeGuesserInterface $mimeTypeGuesser): Response
    {
        $response = new BinaryFileResponse($image_path);
        $response->setEtag(md5_file($image_path));
        $response->setLastModified((new DateTime())->setTimestamp(filemtime($image_path)));
        $response->setMaxAge(3600); // @TODO : read from conf
        $response->setPublic();

        $response->headers->set('Content-Type', $mimeTypeGuesser->guessMimeType($image_path));

        return $response;
    }
}
