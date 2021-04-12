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

use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\Response;
use Phyxo\Conf;
use Phyxo\Image\DerivativeParams;
use Phyxo\Image\Image;
use Phyxo\Image\SizingParams;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class MediaController extends CommonController
{
    protected $image_std_params;
    private $rotation_angle, $original_size, $mimeTypes;

    public function index(Request $request, string $path, string $derivative, string $sizes, string $image_extension, string $mediaCacheDir, Conf $conf,
                        LoggerInterface $logger, ImageStandardParams $image_std_params, ImageRepository $imageRepository, string $rootProjectDir, MimeTypeGuesserInterface $mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;

        $image_path = sprintf('%s.%s', $path, $image_extension);
        if (!empty($sizes)) {
            $derivative_path = sprintf('%s-%s%s.%s', $path, $derivative, $sizes, $image_extension);
        } else {
            $derivative_path = sprintf('%s-%s.%s', $path, $derivative, $image_extension);
        }

        $image_src = sprintf('%s/%s', $rootProjectDir, $image_path);
        $derivative_src = sprintf('%s/%s', $mediaCacheDir, $derivative_path);
        $derivative_params = null;

        $this->image_std_params = $image_std_params;
        foreach ($this->image_std_params->getDefinedTypeMap() as $type => $params) {
            if (DerivativeParams::derivative_to_url($type) === $derivative) {
                $derivative_type = $type;
                $derivative_params = $params;
                break;
            }
        }

        // deal with ./ at the beginning of path
        $image = $imageRepository->findOneByUnsanePath($path . '.' . $image_extension);
        if (is_null($image)) {
            return new Response('Db file path not found ', 404);
        }

        if (!$imageRepository->isAuthorizedToUser($this->getUser()->getForbiddenCategories(), $image->getId())) {
            return new Response('User not allowed to see that image ', 403);
        }

        if (!isset($derivative_type)) {
            if (DerivativeParams::derivative_to_url(ImageStandardParams::IMG_CUSTOM) === $derivative) {
                $derivative_type = ImageStandardParams::IMG_CUSTOM;
            } else {
                return new Response('Unknown parsing type', 400);
            }
        }

        if ($derivative_type === ImageStandardParams::IMG_CUSTOM) {
            $params = $derivative_params = $this->parse_custom_params(array_slice(explode('_', '_' . $sizes), 1));
            $this->image_std_params->applyWatermark($params);

            if ($params->sizing->ideal_size[0] < 20 || $params->sizing->ideal_size[1] < 20) {
                return new Response('Invalid size', 400);
            }
            if ($params->sizing->max_crop < 0 || $params->sizing->max_crop > 1) {
                return new Response('Invalid crop', 400);
            }

            $key = [];
            $params->add_url_tokens($key);
            $key = implode('_', $key);

            if (!$this->image_std_params->hasCustom($key)) {
                return new Response('Size not allowed', 403);
            }
        }

        $params = $derivative_params;

        if (!is_readable($image_src)) {
            return new Response('Image not found ' . $image_src, 404);
        }
        $src_mtime = filemtime($image_src);
        $need_generate = true;

        if (is_readable($derivative_src)) {
            $derivative_mtime = filemtime($derivative_src);
            if ($derivative_mtime < $src_mtime || $derivative_mtime < $params->last_mod_time) {
                $need_generate = true;
                $logger->info(sprintf("Need generate %s", $derivative_path));
            } else {
                $need_generate = false;
            }
        }

        if (!$need_generate) {
            $response = $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path);
            $response->isNotModified($request);

            return $response;
        }

        $coi = null;
        try {
            if ($image->getWidth() > 0 && $image->getHeight()) {
                $this->original_size = [$image->getWidth(), $image->getHeight()];
            }

            $coi = $image->getCoi();
            if (!$image->getRotation()) {
                $this->rotation_angle = Image::getRotationAngle($image_src);
                $image->setRotation(Image::getRotationCodeFromAngle($this->rotation_angle));
                $imageRepository->addOrUpdateImage($image);
            } else {
                $this->rotation_angle = Image::getRotationAngleFromCode($image->getRotation());
            }
        } catch (\Exception $e) {
            return new Response($e->getMessage());
        }

        if (!$this->try_switch_source($params, $derivative_src, $src_mtime) && $params->type === ImageStandardParams::IMG_CUSTOM) {
            $sharpen = 0;
            foreach ($this->image_std_params->getDefinedTypeMap() as $std_params) {
                $sharpen += $std_params->sharpen;
            }
            $params->sharpen = round($sharpen / count($this->image_std_params->getDefinedTypeMap()));
        }

        $image = new Image($image_src);
        $changes = 0;

        // rotate
        if (isset($this->rotation_angle) && $this->rotation_angle !== 0) {
            $changes++;
            $image->rotate($this->rotation_angle);
        }

        // Crop & scale
        $crop_rect = $scaled_size = null;
        $o_size = $d_size = [$image->get_width(), $image->get_height()];
        $params->sizing->compute($o_size, $coi, $crop_rect, $scaled_size);
        if ($crop_rect) {
            $changes++;
            $image->crop($crop_rect->width(), $crop_rect->height(), $crop_rect->l, $crop_rect->t);
        }

        if ($scaled_size) {
            $changes;
            $image->resize($scaled_size[0], $scaled_size[1]);
            $d_size = $scaled_size;
        }

        if ($params->sharpen) {
            $changes += $image->sharpen($params->sharpen);
        }

        if ($params->will_watermark($d_size, $this->image_std_params)) {
            $wm = $this->image_std_params->getWatermark();
            $wm_image = new Image(__DIR__ . '/../../' . $wm->file);
            $wm_size = [$wm_image->get_width(), $wm_image->get_height()];
            if ($d_size[0] < $wm_size[0] or $d_size[1] < $wm_size[1]) {
                $wm_scaling_params = SizingParams::classic($d_size[0], $d_size[1]);
                $wm_scaling_params->compute($wm_size, null, $tmp, $wm_scaled_size);
                $wm_size = $wm_scaled_size;
                $wm_image->resize($wm_scaled_size[0], $wm_scaled_size[1]);
            }
            $x = round(($wm->xpos / 100) * ($d_size[0] - $wm_size[0]));
            $y = round(($wm->ypos / 100) * ($d_size[1] - $wm_size[1]));
            if ($image->compose($wm_image, $x, $y, $wm->opacity)) {
                $changes++;
                if ($wm->xrepeat) {
                    // todo
                    $pad = $wm_size[0] + max(30, round($wm_size[0] / 4));
                    for ($i = -$wm->xrepeat; $i <= $wm->xrepeat; $i++) {
                        if (!$i) {
                            continue;
                        }
                        $x2 = $x + $i * $pad;
                        if ($x2 >= 0 && $x2 + $wm_size[0] < $d_size[0]) {
                            if (!$image->compose($wm_image, $x2, $y, $wm->opacity)) {
                                break;
                            }
                        }
                    }
                }
            }
            $wm_image->destroy();
        }

        $logger->info(sprintf('When generating derivative image, changes ? %d', $changes));

        if ($d_size[0] * $d_size[1] < $conf['derivatives_strip_metadata_threshold']) {// strip metadata for small images
            $image->strip();
        }

        $fs = new Filesystem();
        $fs->mkdir(dirname($mediaCacheDir . '/' . $derivative_path));
        $image->set_compression_quality($this->image_std_params->getQuality());
        $logger->info(sprintf('WRITE %s', $mediaCacheDir . '/' . $derivative_path));
        $image->write($mediaCacheDir . '/' . $derivative_path);
        $image->destroy();
        chmod($mediaCacheDir . '/' . $derivative_path, 0644);

        $f = filemtime($mediaCacheDir . '/' . $derivative_path);

        return $this->makeDerivativeResponse($mediaCacheDir . '/' . $derivative_path);
    }

    private function url_to_size($s)
    {
        $pos = strpos($s, 'x');
        if ($pos === false) {
            return [(int)$s, (int)$s];
        }

        return [(int)substr($s, 0, $pos), (int)substr($s, $pos + 1)];
    }

    private function parse_custom_params($tokens)
    {
        if (count($tokens) < 1) {
            return new Response('Empty array while parsing Sizing', 400);
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
                return new Response('Sizing arr', 400);
            }

            $token = array_shift($tokens);
            $crop = DerivativeParams::char_to_fraction($token);

            $token = array_shift($tokens);
            $min_size = $this->url_to_size($token);
        }

        return new DerivativeParams(new SizingParams($size, $crop, $min_size));
    }

    private function try_switch_source(DerivativeParams $params, string $derivative_path, $original_mtime)
    {
        if (!isset($this->original_size)) {
            return false;
        }

        $original_size = $this->original_size;
        if ($this->rotation_angle === 90 || $this->rotation_angle === 270) {
            $tmp = $original_size[0];
            $original_size[0] = $original_size[1];
            $original_size[1] = $tmp;
        }
        $dsize = $params->compute_final_size($original_size);

        $use_watermark = $params->use_watermark;
        if ($use_watermark) {
            $use_watermark = $params->will_watermark($dsize, $this->image_std_params);
        }

        $candidates = [];
        foreach ($this->image_std_params->getDefinedTypeMap() as $candidate) {
            if ($candidate->type == $params->type) {
                continue;
            }
            if ($candidate->use_watermark != $use_watermark) {
                continue;
            }
            if ($candidate->max_width() < $params->max_width() || $candidate->max_height() < $params->max_height()) {
                continue;
            }
            $candidate_size = $candidate->compute_final_size($original_size);
            if ($dsize != $params->compute_final_size($candidate_size)) {
                continue;
            }

            if ($params->sizing->max_crop == 0) {
                if ($candidate->sizing->max_crop != 0) {
                    continue;
                }
            } else {
                if ($candidate->sizing->max_crop != 0) {
                    continue; // this could be optimized
                }
                if ($candidate_size[0] < $params->sizing->min_size[0] || $candidate_size[1] < $params->sizing->min_size[1]) {
                    continue;
                }
            }
            $candidates[] = $candidate;
        }

        foreach (array_reverse($candidates) as $candidate) {
            $candidate_path = $derivative_path;
            $candidate_path = str_replace('-' . DerivativeParams::derivative_to_url($params->type), '-' . DerivativeParams::derivative_to_url($candidate->type), $candidate_path);

            if (!is_readable($candidate_path)) {
                continue;
            }

            $candidate_mtime = filemtime($candidate_path);
            if ($candidate_mtime === false || $candidate_mtime < $original_mtime || $candidate_mtime < $candidate->last_mod_time) {
                continue;
            }
            $params->use_watermark = false;
            $params->sharpen = min(1, $params->sharpen);
            $this->rotation_angle = 0;

            return true;
        }

        return false;
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
