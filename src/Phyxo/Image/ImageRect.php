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
 * Small utility to manipulate a 'rectangle'.
 */
class ImageRect
{
    private int $l = 0;
    private int $t = 0;
    private int $r;
    private int $b;

    /**
     * @param int[] $l width and height
     */
    public function __construct(array $l)
    {
        [$this->r, $this->b] = $l;
    }

    public function width(): int
    {
        return $this->r - $this->l;
    }

    public function height(): int
    {
        return $this->b - $this->t;
    }

    /**
     * Crops horizontally this rectangle by increasing left side and/or reducing the right side.
     *
     * @param int    $pixels - the amount to substract from the width
     * @param string $coi    - a 4 character string (or null) containing the center of interest
     */
    public function crop_h(int $pixels, string $coi = ''): void
    {
        if ($this->width() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if ($coi !== '') {
            $coil = floor($this->r * DerivativeParams::charToFraction($coi[0]));
            $coir = ceil($this->r * DerivativeParams::charToFraction($coi[2]));
            $availableL = $coil > $this->l ? $coil - $this->l : 0;
            $availableR = $coir < $this->r ? $this->r - $coir : 0;
            if ($availableL + $availableR >= $pixels) {
                if ($availableL < $tlcrop) {
                    $tlcrop = $availableL;
                } elseif ($availableR < $tlcrop) {
                    $tlcrop = $pixels - $availableR;
                }
            }
        }

        $this->l += $tlcrop;
        $this->r -= $pixels - $tlcrop;
    }

    /**
     * Crops vertically this rectangle by increasing top side and/or reducing the bottom side.
     *
     * @param int    $pixels - the amount to substract from the height
     * @param string $coi    - a 4 character string (or null) containing the center of interest
     */
    public function crop_v(int $pixels, string $coi = ''): void
    {
        if ($this->height() <= $pixels) {
            return;
        }

        $tlcrop = floor($pixels / 2);

        if ($coi !== '') {
            $coit = floor($this->b * DerivativeParams::charToFraction($coi[1]));
            $coib = ceil($this->b * DerivativeParams::charToFraction($coi[3]));
            $availableT = $coit > $this->t ? $coit - $this->t : 0;
            $availableB = $coib < $this->b ? $this->b - $coib : 0;
            if ($availableT + $availableB >= $pixels) {
                if ($availableT < $tlcrop) {
                    $tlcrop = $availableT;
                } elseif ($availableB < $tlcrop) {
                    $tlcrop = $pixels - $availableB;
                }
            }
        }

        $this->t += $tlcrop;
        $this->b -= $pixels - $tlcrop;
    }

    public function getLeft(): int
    {
        return $this->l;
    }

    public function getTop(): int
    {
        return $this->t;
    }
}
