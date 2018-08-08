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

use Phyxo\Image\ImageStdParams;
use Phyxo\Image\SizingParams;

/**
 * All needed parameters to generate a derivative image.
 */
class DerivativeParams
{
    /** @var SizingParams */
    public $sizing;
    /** @var string among IMG_* */
    public $type = IMG_CUSTOM;
    /** @var int used for non-custom images to regenerate the cached files */
    public $last_mod_time = 0;
    /** @var bool */
    public $use_watermark = false;
    /** @var float from 0=no sharpening to 1=max sharpening */
    public $sharpen = 0;

    /**
     * @param SizingParams $sizing
     */
    public function __construct(SizingParams $sizing)
    {
        $this->sizing = $sizing;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return array('last_mod_time', 'sizing', 'sharpen');
    }

    /**
     * Adds tokens depending on sizing configuration.
     *
     * @param array &$tokens
     */
    public function add_url_tokens(&$tokens)
    {
        $this->sizing->add_url_tokens($tokens);
    }

    /**
     * @return int[]
     */
    public function compute_final_size($in_size)
    {
        $this->sizing->compute($in_size, null, $crop_rect, $scale_size);
        return $scale_size != null ? $scale_size : $in_size;
    }

    /**
     * @return int
     */
    public function max_width()
    {
        return $this->sizing->ideal_size[0];
    }

    /**
     * @return int
     */
    public function max_height()
    {
        return $this->sizing->ideal_size[1];
    }

    /**
     * @todo : description of DerivativeParams::is_identity
     *
     * @return bool
     */
    public function is_identity($in_size)
    {
        if ($in_size[0] > $this->sizing->ideal_size[0] || $in_size[1] > $this->sizing->ideal_size[1]) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function will_watermark($out_size)
    {
        if ($this->use_watermark) {
            $min_size = ImageStdParams::get_watermark()->min_size;
            return $min_size[0] <= $out_size[0] || $min_size[1] <= $out_size[1];
        }
        return false;
    }

    //
    /**
     * Formats a size name into a 2 chars identifier usable in filename.
     *
     * @param string $t one of IMG_*
     * @return string
     */
    public static function derivative_to_url($t)
    {
        return substr($t, 0, 2);
    }

    /**
     * Formats a size array into a identifier usable in filename.
     *
     * @param int[] $s
     * @return string
     */
    public static function size_to_url($s)
    {
        if ($s[0] == $s[1]) {
            return $s[0];
        }
        return $s[0] . 'x' . $s[1];
    }

    /**
     * @param int[] $s1
     * @param int[] $s2
     * @return bool
     */
    public static function size_equals($s1, $s2)
    {
        return ($s1[0] == $s2[0] && $s1[1] == $s2[1]);
    }

    /**
     * Converts a char a-z into a float.
     *
     * @param string
     * @return float
     */
    public static function char_to_fraction($c)
    {
        return (ord($c) - ord('a')) / 25;
    }

    /**
     * Converts a float into a char a-z.
     *
     * @param float
     * @return string
     */
    public static function fraction_to_char($f)
    {
        return chr(ord('a') + round($f * 25));
    }
}
