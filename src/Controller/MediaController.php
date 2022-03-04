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

use App\ImageLibraryGuesser;
use App\Repository\ImageRepository;
use App\Security\AppUserService;
use Symfony\Component\HttpFoundation\Response;
use Phyxo\Conf;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\ImageOptimizer;
use Phyxo\Image\SizingParams;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class MediaController extends CommonController
{
    protected ImageStandardParams $image_std_params;
    private MimeTypeGuesserInterface $mimeTypes;
    private bool $forAdmin = false;

    public function index(
        Request $request,
        string $path,
        string $derivative,
        string $sizes,
        string $image_extension,
        string $mediaCacheDir,
        Conf $conf,
        ImageStandardParams $image_std_params,
        ImageRepository $imageRepository,
        string $rootProjectDir,
        MimeTypeGuesserInterface $mimeTypes,
        ImageLibraryGuesser $imageLibraryGuesser,
        AppUserService $appUserService
    ): Response {
        $this->mimeTypes = $mimeTypes;

        $image_path = sprintf('%s.%s', $path, $image_extension);
        if (!empty($sizes)) {
            $derivative_path = sprintf('%s-%s%s.%s', $path, $derivative, $sizes, $image_extension);
        } else {
            $derivative_path = sprintf('%s-%s.%s', $path, $derivative, $image_extension);
        }

        $image_src = sprintf('%s/%s', $rootProjectDir, $image_path);
        $derivative_src = sprintf('%s/%s', $mediaCacheDir, $derivative_path);
        $derivative_params = $image_std_params->getParamsFromDerivative($derivative);
        if (!is_null($derivative_params)) {
            $derivative_type = $derivative_params->type;
        } else {
            $derivative_type = null;
        }

        // deal with ./ at the beginning of path
        $image = $imageRepository->findOneByUnsanePath($path . '.' . $image_extension);
        if (is_null($image)) {
            return new Response('Db file path not found ', 404);
        }

        if (!$this->forAdmin && !$imageRepository->isAuthorizedToUser($image->getId(), $appUserService->getUser()->getUserInfos()->getForbiddenAlbums())) {
            return new Response('User not allowed to see that image ', 403);
        }

        if (is_null($derivative_type)) {
            if (DerivativeParams::derivative_to_url(ImageStandardParams::IMG_CUSTOM) === $derivative) {
                $derivative_type = ImageStandardParams::IMG_CUSTOM;
            } elseif ($derivative === 'original') {
                $original_size = [$image->getWidth(), $image->getHeight()];
                $derivative_params = new DerivativeParams(new SizingParams($original_size));
                $derivative_type = ImageStandardParams::IMG_ORIGINAL;
            } else {
                return new Response('Unknown parsing type', 400);
            }
        }

        if ($derivative_type === ImageStandardParams::IMG_CUSTOM) {
            try {
                $derivative_params = $derivative_params = $this->parse_custom_params(array_slice(explode('_', '_' . $sizes), 1));
            } catch (\Exception $e) {
                return new Response($e->getMessage());
            }

            $image_std_params->applyWatermark($derivative_params);

            if ($derivative_params->sizing->ideal_size[0] < 20 || $derivative_params->sizing->ideal_size[1] < 20) {
                return new Response('Invalid size', 400);
            }
            if ($derivative_params->sizing->max_crop < 0 || $derivative_params->sizing->max_crop > 1) {
                return new Response('Invalid crop', 400);
            }

            $key = [];
            $derivative_params->add_url_tokens($key);
            $key = implode('_', $key);

            if (!$image_std_params->hasCustom($key)) {
                return new Response('Size not allowed', 403);
            }
        }

        if (!is_readable($image_src)) {
            return new Response('Image not found ' . $image_src, 404);
        }
        $src_mtime = filemtime($image_src);
        $need_generate = true;

        if (is_readable($derivative_src)) {
            $derivative_mtime = filemtime($derivative_src);
            if ($derivative_mtime < $src_mtime || $derivative_mtime < $derivative_params->last_mod_time) {
                $need_generate = true;
            } else {
                $need_generate = false;
            }
        }

        if (!$need_generate) {
            $response = $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path);
            $response->isNotModified($request);

            return $response;
        }

        $coi = '';
        $imageOptimizer = new ImageOptimizer($image_src, $imageLibraryGuesser->getLibrary());

        try {
            if ($image->getWidth() > 0 && $image->getHeight()) {
                $original_size = [$image->getWidth(), $image->getHeight()];
            }

            $coi = (string) $image->getCoi();
            if ($image->getRotation() === 0 || is_null($image->getRotation())) {
                $rotation_angle = $imageOptimizer->getRotationAngle();
                $image->setRotation($imageOptimizer->getRotationCodeFromAngle($rotation_angle));
                $imageRepository->addOrUpdateImage($image);
            } else {
                $rotation_angle = $imageOptimizer->getRotationAngleFromCode($image->getRotation());
            }
        } catch (\Exception $e) {
            return new Response($e->getMessage());
        }

        // rotate
        if ($rotation_angle !== 0) {
            $imageOptimizer->rotate($rotation_angle);
        }

        // Crop & scale
        $crop_rect = $scaled_size = null;
        $o_size = $d_size = [$imageOptimizer->getWidth(), $imageOptimizer->getHeight()];
        $derivative_params->sizing->compute($o_size, $coi, $crop_rect, $scaled_size);
        if ($crop_rect) {
            $imageOptimizer->crop($crop_rect->width(), $crop_rect->height(), $crop_rect->l, $crop_rect->t);
        }

        if ($scaled_size) {
            $imageOptimizer->resize($scaled_size[0], $scaled_size[1]);
            $d_size = $scaled_size;
        }

        if ($derivative_params->will_watermark($d_size, $image_std_params)) {
            $wm = $image_std_params->getWatermark();
            $wm_image = new ImageOptimizer(__DIR__ . '/../../' . $wm->file, $imageLibraryGuesser->getLibrary());
            $wm_size = [$wm_image->getWidth(), $wm_image->getHeight()];
            if ($d_size[0] < $wm_size[0] or $d_size[1] < $wm_size[1]) {
                $wm_scaling_params = SizingParams::classic($d_size[0], $d_size[1]);
                $wm_scaling_params->compute($wm_size, '', $tmp, $wm_scaled_size);
                $wm_size = $wm_scaled_size;
                $wm_image->resize($wm_scaled_size[0], $wm_scaled_size[1]);
            }
            $x = round(($wm->xpos / 100) * ($d_size[0] - $wm_size[0]));
            $y = round(($wm->ypos / 100) * ($d_size[1] - $wm_size[1]));

            if ($imageOptimizer->compose($wm_image->getImage(), $x, $y, $wm->opacity)) {
                if ($wm->xrepeat) {
                    // todo
                    $pad = $wm_size[0] + max(30, round($wm_size[0] / 4));
                    for ($i = -$wm->xrepeat; $i <= $wm->xrepeat; $i++) {
                        if (!$i) {
                            continue;
                        }
                        $x2 = $x + $i * $pad;
                        if ($x2 >= 0 && $x2 + $wm_size[0] < $d_size[0]) {
                            if (!$imageOptimizer->compose($wm_image->getImage(), $x2, $y, $wm->opacity)) {
                                break;
                            }
                        }
                    }
                }
            }
            $wm_image->destroy();
        }

        if ($d_size[0] * $d_size[1] < $conf['derivatives_strip_metadata_threshold']) {// strip metadata for small images
            $imageOptimizer->strip();
        }

        $fs = new Filesystem();
        $fs->mkdir(dirname($mediaCacheDir . '/' . $derivative_path));
        $imageOptimizer->setCompressionQuality($image_std_params->getQuality());
        $imageOptimizer->write($mediaCacheDir . '/' . $derivative_path);
        $imageOptimizer->destroy();
        chmod($mediaCacheDir . '/' . $derivative_path, 0644);

        return $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path);
    }

    public function mediaForAdmin(string $path, string $derivative, string $image_extension): Response
    {
        $this->forAdmin = true;

        return $this->forward(
            'App\Controller\MediaController::index',
            ['path' => $path, 'derivative' => $derivative, 'image_extension' => $image_extension, 'sizes' => '']
        );
    }

    /**
     * @return array<int, int>
     */
    private function url_to_size(string $s): array
    {
        $pos = strpos($s, 'x');
        if ($pos === false) {
            return [(int)$s, (int)$s];
        }

        return [(int)substr($s, 0, $pos), (int)substr($s, $pos + 1)];
    }

    /**
     * @param array<int, string> $tokens
     */
    private function parse_custom_params(array $tokens): DerivativeParams
    {
        if (count($tokens) < 1) {
            throw new \Exception('Empty array while parsing Sizing', 400);
        }

        $crop = 0;
        $min_size = null;

        $token = array_shift($tokens);
        if ($token[0] == 's') {
            $size = $this->url_to_size(substr($token, 1));
        } elseif ($token[0] == 'e') {
            $crop = 1;
            $size = $min_size = $this->url_to_size(substr($token, 1));
        } else {
            $size = $this->url_to_size($token);
            if (count($tokens) < 2) {
                throw new \Exception('Sizing arr', 400);
            }

            $token = array_shift($tokens);
            $crop = DerivativeParams::char_to_fraction($token);

            $token = array_shift($tokens);
            $min_size = $this->url_to_size($token);
        }

        return new DerivativeParams(new SizingParams($size, $crop, $min_size));
    }

    protected function makeDerivativeResponse(string $image_path): Response
    {
        $response = new BinaryFileResponse($image_path);
        $response->setEtag(md5_file($image_path));
        $response->setLastModified((new \DateTime())->setTimestamp(filemtime($image_path)));
        $response->setMaxAge(3600); //@TODO : read from conf
        $response->setPublic();
        $response->headers->set('Content-Type', $this->mimeTypes->guessMimeType($image_path));

        return $response;
    }
}
