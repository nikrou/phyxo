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

/**
 * Paramaters for derivative scaling and cropping.
 * Instance of this class contained by DerivativeParams class.
 */
class SizingParams
{
    /**
     * $ideal_size - two elements array of maximum output dimensions (width, height)
     * $max_crop - from 0=no cropping to 1= max cropping (100% of width/height) expressed as a factor of the input width/height
     * $min_size - (used only if _$max_crop_ !=0) two elements array of output dimensions (width, height)
     * @param int[] $ideal_size
     * @param int[] $min_size
     */
    public function __construct(private array $ideal_size, private readonly float $max_crop = 0, private array $min_size = [])
    {
    }

    /**
     * Returns a simple SizingParams object.
     */
    public static function classic(int $w, int $h): SizingParams
    {
        return new SizingParams([$w, $h]);
    }

    /**
     * Returns a square SizingParams object.
     */
    public static function square(int $w): SizingParams
    {
        return new SizingParams([$w, $w], 1, [$w, $w]);
    }

    /**
     * Adds tokens depending on sizing configuration.
     *
     * @param array<string> $tokens
     */
    public function addUrlTokens(array &$tokens): void
    {
        if ($this->max_crop == 0) {
            $tokens[] = 's' . DerivativeParams::sizeToUrl($this->ideal_size);
        } elseif ($this->max_crop == 1 && DerivativeParams::sizeEquals($this->ideal_size, $this->min_size)) {
            $tokens[] = 'e' . DerivativeParams::sizeToUrl($this->ideal_size);
        } else {
            $tokens[] = DerivativeParams::sizeToUrl($this->ideal_size);
            $tokens[] = DerivativeParams::fractionToChar($this->max_crop);
            $tokens[] = DerivativeParams::sizeToUrl($this->min_size);
        }
    }

    /**
     * Calculates the cropping rectangle and the scaled size for an input image size.
     *
     * @param array{0:int, 1:int} $in_size - two elements array of input dimensions (width, height)
     * @param string $coi - four character encoded string containing the center of interest (unused if max_crop=0)
     * @param ImageRect &$crop_rect - ImageRect containing the cropping rectangle or null if cropping is not required
     * @param array{0:int|float, 1:int|float}|array{} &$scale_size - two elements array containing width and height of the scaled image
     */
    public function compute(array $in_size, ?ImageRect &$crop_rect, array &$scale_size, string $coi = ''): void
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
                        $maxCropPx = (int) round($this->max_crop * $destCrop->width());
                        $destCrop->crop_h(min($idealCropPx, $maxCropPx), $coi);
                    }
                } else {
                    $w = $destCrop->width() / $ratio_h;
                    if ($w < $this->min_size[0]) {
                        $idealCropPx = $destCrop->height() - floor($destCrop->width() * $this->ideal_size[1] / $this->min_size[0]);
                        $maxCropPx = (int) round($this->max_crop * $destCrop->height());
                        $destCrop->crop_v(min($idealCropPx, $maxCropPx), $coi);
                    }
                }
            }
        }

        $scale_size = [$destCrop->width(), $destCrop->height()];

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
            $scale_size = [];
        }

        $crop_rect = null;
        if ($destCrop->width() != $in_size[0] || $destCrop->height() != $in_size[1]) {
            $crop_rect = $destCrop;
        }
    }

    /**
     * @return array{0:int, 1:int}
     */
    public function getIdealSize(): array
    {
        return $this->ideal_size;
    }

    public function getMaxCrop(): float
    {
        return $this->max_crop;
    }

    /**
     * @return array{0:int, 1:int}
     */
    public function getMinSize(): array
    {
        return $this->min_size;
    }
}
