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

use Phyxo\Image\ImageRect;
use Phyxo\Image\DerivativeParams;

/**
 * Paramaters for derivative scaling and cropping.
 * Instance of this class contained by DerivativeParams class.
 */
class SizingParams
{
    /** @var int[] */
    public $ideal_size;
    /** @var float */
    public $max_crop;
    /** @var int[] */
    public $min_size;

    /**
     * @param int[] $ideal_size - two element array of maximum output dimensions (width, height)
     * @param float $max_crop - from 0=no cropping to 1= max cropping (100% of width/height);
     *    expressed as a factor of the input width/height
     * @param int[] $min_size - (used only if _$max_crop_ !=0) two element array of output dimensions (width, height)
     */
    public function __construct($ideal_size, $max_crop = 0, $min_size = null)
    {
        $this->ideal_size = $ideal_size;
        $this->max_crop = $max_crop;
        $this->min_size = $min_size;
    }

    /**
     * Returns a simple SizingParams object.
     *
     * @param int $w
     * @param int $h
     * @return SizingParams
     */
    public static function classic($w, $h)
    {
        return new SizingParams(array($w, $h));
    }

    /**
     * Returns a square SizingParams object.
     *
     * @param int $x
     * @return SizingParams
     */
    public static function square($w)
    {
        return new SizingParams(array($w, $w), 1, array($w, $w));
    }

    /**
     * Adds tokens depending on sizing configuration.
     *
     * @param array &$tokens
     */
    public function add_url_tokens(&$tokens)
    {
        if ($this->max_crop == 0) {
            $tokens[] = 's' . DerivativeParams::size_to_url($this->ideal_size);
        } elseif ($this->max_crop == 1 && DerivativeParams::size_equals($this->ideal_size, $this->min_size)) {
            $tokens[] = 'e' . DerivativeParams::size_to_url($this->ideal_size);
        } else {
            $tokens[] = DerivativeParams::size_to_url($this->ideal_size);
            $tokens[] = DerivativeParams::fraction_to_char($this->max_crop);
            $tokens[] = DerivativeParams::size_to_url($this->min_size);
        }
    }

    /**
     * Calculates the cropping rectangle and the scaled size for an input image size.
     *
     * @param int[] $in_size - two element array of input dimensions (width, height)
     * @param string $coi - four character encoded string containing the center of interest (unused if max_crop=0)
     * @param ImageRect &$crop_rect - ImageRect containing the cropping rectangle or null if cropping is not required
     * @param int[] &$scale_size - two element array containing width and height of the scaled image
     */
    public function compute($in_size, $coi, &$crop_rect, &$scale_size)
    {
        $destCrop = new ImageRect($in_size);

        if ($this->max_crop > 0) {
            $ratio_w = $destCrop->width() / $this->ideal_size[0];
            $ratio_h = $destCrop->height() / $this->ideal_size[1];
            if ($ratio_w > 1 || $ratio_h > 1) {
                if ($ratio_w > $ratio_h) {
                    $h = $destCrop->height() / $ratio_w;
                    if ($h < $this->min_size[1]) {
                        $idealCropPx = $destCrop->width() - floor($destCrop->height() * $this->ideal_size[0] / $this->min_size[1]);
                        $maxCropPx = round($this->max_crop * $destCrop->width());
                        $destCrop->crop_h(min($idealCropPx, $maxCropPx), $coi);
                    }
                } else {
                    $w = $destCrop->width() / $ratio_h;
                    if ($w < $this->min_size[0]) {
                        $idealCropPx = $destCrop->height() - floor($destCrop->width() * $this->ideal_size[1] / $this->min_size[0]);
                        $maxCropPx = round($this->max_crop * $destCrop->height());
                        $destCrop->crop_v(min($idealCropPx, $maxCropPx), $coi);
                    }
                }
            }
        }

        $scale_size = array($destCrop->width(), $destCrop->height());

        $ratio_w = $destCrop->width() / $this->ideal_size[0];
        $ratio_h = $destCrop->height() / $this->ideal_size[1];
        if ($ratio_w > 1 || $ratio_h > 1) {
            if ($ratio_w > $ratio_h) {
                $scale_size[0] = $this->ideal_size[0];
                $scale_size[1] = floor(1e-6 + $scale_size[1] / $ratio_w);
            } else {
                $scale_size[0] = floor(1e-6 + $scale_size[0] / $ratio_h);
                $scale_size[1] = $this->ideal_size[1];
            }
        } else {
            $scale_size = null;
        }

        $crop_rect = null;
        if ($destCrop->width() != $in_size[0] || $destCrop->height() != $in_size[1]) {
            $crop_rect = $destCrop;
        }
    }
}
