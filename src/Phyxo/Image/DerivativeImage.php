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

use App\Entity\Image;

/**
 * Holds information (path, url, dimensions) about a derivative image.
 * A derivative image is constructed from a source image (Image class)
 * and derivative parameters (DerivativeParams class).
 */
class DerivativeImage
{
    private $image, $params, $image_std_params;

    // @TODO $params is DerivativeParams but problem in Ws/Main
    public function __construct(Image $image, $params, ImageStandardParams $image_std_params)
    {
        $this->image = $image;
        $this->params = $params;
        $this->image_std_params = $image_std_params;
    }

    public function getExtension(): string
    {
        return $this->image->getExtension();
    }

    public function getPathBasename(): string
    {
        return $this->image->getPathBasename();
    }

    public function getType(): string
    {
        if (is_null($this->params)) {
            return ImageStandardParams::IMG_ORIGINAL;
        }

        return $this->params->type;
    }

    public function getUrlType(): string
    {
        if (is_null($this->params)) {
            return ImageStandardParams::IMG_ORIGINAL;
        }

        return DerivativeParams::derivative_to_url($this->params->type);
    }

    public function getSize(): array
    {
        if (!$this->image->getWidth() || !$this->image->getHeight()) {
            return [];
        }

        $width = $this->image->getWidth();
        $height = $this->image->getHeight();

        $rotation = intval($this->image->getRotation()) % 4;
        // 1 or 5 =>  90 clockwise
        // 3 or 7 => 270 clockwise
        if ($rotation % 2) {
            $width = $this->image->getHeight();
            $height = $this->image->getWidth();
        }

        if ($this->params == null) {
            return [$width, $height];
        }

        return $this->params->compute_final_size([$width, $height]);
    }

    public function getUrlSize(): string
    {
        $tokens = [];

        if ($this->params->type === ImageStandardParams::IMG_CUSTOM) {
            $this->params->add_url_tokens($tokens);
        }

        return sprintf('%s', implode('', $tokens));
    }

    /**
     * Returns literal size: $widthx$height.
     */
    public function getLiteralSize(): string
    {
        $size = $this->getSize();
        if (!$size) {
            return '';
        }

        return $size[0] . ' x ' . $size[1];
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
        if (count($this->getSize()) > 0 && $this->params->is_identity($this->getSize())) {
            // the source image is smaller than what we should do - we do not upsample
            if (!$this->params->will_watermark($this->getSize(), $this->image_std_params) && !$this->image->getRotation()) {
                // no watermark, no rotation required -> we will use the source image
                return [
                    'path' => $this->getPathBasename(),
                    'derivative' => substr((string) $this->params->type, 0, 2),
                    'sizes' => '',
                    'image_extension' => $this->getExtension()
                ];
            }

            $defined_types = array_keys($this->image_std_params->getDefinedTypeMap());
            for ($i = 0; $i < count($defined_types); $i++) {
                if ($defined_types[$i] == $this->params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = $this->image_std_params->getByType($defined_types[$i]);
                        if ($smaller->sizing->max_crop === $this->params->sizing->max_crop && $smaller->is_identity($this->getSize())) {
                            $this->params = $smaller;
                            return [
                                'path' => $this->getPathBasename(),
                                'derivative' => substr($this->params->type, 0, 2),
                                'sizes' => '',
                                'image_extension' => $this->getExtension()
                            ];
                        }
                    }
                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr((string) $this->params->type, 0, 2);

        if ($this->params->type === ImageStandardParams::IMG_CUSTOM) {
            $this->params->add_url_tokens($tokens);
        }

        return [
            'path' => $this->getPathBasename(),
            'derivative' => substr((string) $this->params->type, 0, 2),
            'sizes' => '',
            'image_extension' => $this->getExtension()
        ];
    }

    /**
     * @TODO : documentation of DerivativeImage::build
     */
    private function build(&$params, &$rel_path, &$rel_url)
    {
        if (count($this->getSize()) > 0 && $params->is_identity($this->getSize())) {
            // the source image is smaller than what we should do - we do not upsample
            if (!$params->will_watermark($this->getSize(), $this->image_std_params) && !$this->image->getRotation()) {
                // no watermark, no rotation required -> we will use the source image
                $params = null;
                $rel_path = $rel_url = $this->image->getPath();
                return;
            }
            $defined_types = array_keys($this->image_std_params->getDefinedTypeMap());
            for ($i = 0; $i < count($defined_types); $i++) {
                if ($defined_types[$i] == $params->type) {
                    for ($i--; $i >= 0; $i--) {
                        $smaller = $this->image_std_params->getByType($defined_types[$i]);
                        if ($smaller->sizing->max_crop == $params->sizing->max_crop && $smaller->is_identity($this->getSize())) {
                            $params = $smaller;
                            $this->build($params, $rel_path, $rel_url);
                            return;
                        }
                    }
                    break;
                }
            }
        }

        $tokens = [];
        $tokens[] = substr((string) $params->type, 0, 2);

        if ($params->type === ImageStandardParams::IMG_CUSTOM) {
            $params->add_url_tokens($tokens);
        }

        $loc = $this->image->getPath();
        if (substr_compare($loc, './', 0, 2) == 0) {
            $loc = substr($loc, 2);
        } elseif (substr_compare($loc, '../', 0, 3) == 0) {
            $loc = substr($loc, 3);
        }
        $loc = substr_replace($loc, '-' . implode('_', $tokens), strrpos($loc, '.'), 0);
        $rel_url = 'media' . '/' . $loc;
    }

    public function same_as_source(): bool
    {
        return $this->params == null;
    }

    public function is_cached(): bool
    {
        return true;
    }
}
