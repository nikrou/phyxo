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

namespace Phyxo\Image;

use Phyxo\Image\SrcImage;
use Phyxo\Functions\Plugin;
use Phyxo\Functions\Utils;

/**
 * Holds information (path, url, dimensions) about a derivative image.
 * A derivative image is constructed from a source image (SrcImage class)
 * and derivative parameters (DerivativeParams class).
 */
class DerivativeImage
{
    private $src_image, $params, $rel_path, $rel_url, $image_std_params;

    public function __construct(SrcImage $src_image, DerivativeParams $params, ImageStandardParams $image_std_params)
    {
        $this->src_image = $src_image;
        $this->params = $params;
        $this->image_std_params = $image_std_params;
    }

    /**
     * Generates the url of a thumbnail.
     */
    public function relativeThumbInfos(): array
    {
        return $this->buildInfos();
    }

    private function buildInfos(): array
    {
        if ($this->src_image->has_size() && $this->params->is_identity($this->src_image->get_size())) {
            // the source image is smaller than what we should do - we do not upsample
            if (!$this->params->will_watermark($this->src_image->get_size(), $this->image_std_params) && !$this->src_image->rotation) {
                // no watermark, no rotation required -> we will use the source image
                return [
                    'path' => Utils::get_filename_wo_extension($this->src_image->rel_path),
                    'derivative' => substr($this->params->type, 0, 2),
                    'sizes' => '',
                    'image_extension' => Utils::get_extension($this->src_image->rel_path)
                ];
            }

            $defined_types = array_keys($this->image_std_params->getDefinedTypeMap());
            for ($i = 0; $i < count($defined_types); $i++) {
                if ($defined_types[$i] == $this->params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = $this->image_std_params->getByType($defined_types[$i]);
                        if ($smaller->sizing->max_crop === $this->params->sizing->max_crop && $smaller->is_identity($this->src_image->get_size())) {
                            $this->params = $smaller;
                            return [
                                'path' => Utils::get_filename_wo_extension($this->src_image->rel_path),
                                'derivative' => substr($this->params->type, 0, 2),
                                'sizes' => '',
                                'image_extension' => Utils::get_extension($this->src_image->rel_path)
                            ];
                        }
                    }
                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr($this->params->type, 0, 2);

        if ($this->params->type === ImageStandardParams::IMG_CUSTOM) {
            $this->params->add_url_tokens($tokens);
        }

        return [
            'path' => Utils::get_filename_wo_extension($this->src_image->rel_path),
            'derivative' => substr($this->params->type, 0, 2),
            'sizes' => '',
            'image_extension' => Utils::get_extension($this->src_image->rel_path)
        ];
    }

    /**
     * Generates the url for a particular photo size.
     */
    public function getUrl(): string
    {
        if (empty($this->paramsms)) { //@TODO: why params can be empty ?
            return $this->src_image->getUrl();
        }

        $this->build($this->src_image, $this->params, $rel_path, $rel_url);

        return \Phyxo\Functions\URL::embellish_url(
            Plugin::trigger_change(
                'get_derivative_url',
                \Phyxo\Functions\URL::get_absolute_root_url() . $rel_url,
                $this->params,
                $this->src_image,
                $rel_url
            )
        );
    }

    /**
     * @TODO : documentation of DerivativeImage::build
     */
    private function build($src, &$params, &$rel_path, &$rel_url, &$is_cached = null)
    {
        global $conf;

        if ($src->has_size() && $params->is_identity($src->get_size())) {
            // the source image is smaller than what we should do - we do not upsample
            if (!$params->will_watermark($src->get_size(), $this->image_std_params) && !$src->rotation) {
                // no watermark, no rotation required -> we will use the source image
                $params = null;
                $rel_path = $rel_url = $src->rel_path;
                return;
            }
            $defined_types = array_keys($this->image_std_params->getDefinedTypeMap());
            for ($i = 0; $i < count($defined_types); $i++) {
                if ($defined_types[$i] == $params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = $this->image_std_params->getByType($defined_types[$i]);
                        if ($smaller->sizing->max_crop == $params->sizing->max_crop && $smaller->is_identity($src->get_size())) {
                            $params = $smaller;
                            $this->build($src, $params, $rel_path, $rel_url, $is_cached);
                            return;
                        }
                    }
                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr($params->type, 0, 2);

        if ($params->type === ImageStandardParams::IMG_CUSTOM) {
            $params->add_url_tokens($tokens);
        }

        $loc = $src->rel_path;
        if (substr_compare($loc, './', 0, 2) == 0) {
            $loc = substr($loc, 2);
        } elseif (substr_compare($loc, '../', 0, 3) == 0) {
            $loc = substr($loc, 3);
        }
        $loc = substr_replace($loc, '-' . implode('_', $tokens), strrpos($loc, '.'), 0);

        $rel_path = $conf['data_location'] . 'i/' . $loc;

        $url_style = $conf['derivative_url_style'];
        if (!$url_style) {
            if (is_readable(__DIR__ . '/../../../' . $rel_path)) {
                if (filemtime(__DIR__ . '/../../../' . $rel_path) < $params->last_mod_time) {
                    $is_cached = false;
                    $url_style = 2;
                } else {
                    $url_style = 1;
                }

            } else {
                $is_cached = false;
                $url_style = 2;
            }

            // $mtime = @filemtime(__DIR__ . '/../../../' . $rel_path);
            // if ($mtime === false or $mtime < $params->last_mod_time) {
            //     $is_cached = false;
            //     $url_style = 2;
            // } else {
            //     $url_style = 1;
            // }
        }

        if ($url_style == 2) {
            $rel_url = 'media';
            // if ($conf['php_extension_in_urls']) $rel_url .= '.php';
            // if ($conf['question_mark_in_urls']) $rel_url .= '?';
            $rel_url .= '/' . $loc;
        } else {
            $rel_url = $rel_path;
        }
    }

    public function get_path(): string
    {
        return __DIR__ . '/../../../' . $this->rel_path;
    }

    public function same_as_source(): bool
    {
        return $this->params == null;
    }

    /**
     * @return string one if IMG_* or 'Original'
     */
    public function get_type()
    {
        if ($this->params == null) {
            return 'Original';
        }

        return $this->params->type;
    }

    /**
     * @return int[]
     */
    public function get_size()
    {
        if ($this->params == null) {
            return $this->src_image->get_size();
        }

        return $this->params->compute_final_size($this->src_image->get_size());
    }

    /**
     * Returns the size as CSS rule.
     *
     * @return string
     */
    public function get_size_css()
    {
        $size = $this->get_size();
        if ($size) {
            return 'width:' . $size[0] . 'px; height:' . $size[1] . 'px';
        }
    }

    /**
     * Returns the size as HTML attributes.
     *
     * @return string
     */
    public function get_size_htm()
    {
        $size = $this->get_size();
        if ($size) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }
    }

    /**
     * Returns literal size: $widthx$height.
     *
     * @return string
     */
    public function get_size_hr()
    {
        $size = $this->get_size();
        if ($size) {
            return $size[0] . ' x ' . $size[1];
        }
    }

    /**
     * @param int $maxw
     * @param int $mawh
     * @return int[]
     */
    public function get_scaled_size($maxw, $maxh)
    {
        $size = $this->get_size();
        if ($size) {
            $ratio_w = $size[0] / $maxw;
            $ratio_h = $size[1] / $maxh;
            if ($ratio_w > 1 || $ratio_h > 1) {
                if ($ratio_w > $ratio_h) {
                    $size[0] = $maxw;
                    $size[1] = floor($size[1] / $ratio_w);
                } else {
                    $size[0] = floor($size[0] / $ratio_h);
                    $size[1] = $maxh;
                }
            }
        }
        return $size;
    }

    public function get_ratio()
    {
        $size = $this->get_size();

        return $size[0] / $size[1];
    }

    /**
     * Returns the scaled size as HTML attributes.
     *
     * @param int $maxw
     * @param int $mawh
     * @return string
     */
    public function get_scaled_size_htm($maxw = 9999, $maxh = 9999)
    {
        $size = $this->get_scaled_size($maxw, $maxh);
        if ($size) {
            return 'width="' . $size[0] . '" height="' . $size[1] . '"';
        }
    }

    /**
     * @return bool
     */
    public function is_cached()
    {
        return true;
    }
}
