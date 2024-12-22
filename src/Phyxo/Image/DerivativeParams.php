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

use App\Enum\ImageSizeType;

/**
 * All needed parameters to generate a derivative image.
 */
class DerivativeParams
{
    public ImageSizeType $type = ImageSizeType::CUSTOM;

    /** @var int used for non-custom images to regenerate the cached files */
    public $last_mod_time = 0;

    /** @var bool */
    public $use_watermark = false;

    public function __construct(public SizingParams $sizing)
    {
    }

    public function __sleep(): array
    {
        return ['last_mod_time', 'sizing'];
    }

    /**
     * Adds tokens depending on sizing configuration.
     */
    public function add_url_tokens(array &$tokens)
    {
        $this->sizing->add_url_tokens($tokens);
    }

    public function compute_final_size($in_size): array
    {
        $crop_rect = null;
        $scale_size = '';
        $this->sizing->compute($in_size, $crop_rect, $scale_size);

        return $scale_size ?? $in_size;
    }

    public function max_width(): int
    {
        return $this->sizing->ideal_size[0];
    }

    public function max_height(): int
    {
        return $this->sizing->ideal_size[1];
    }

    /**
     * @TODO : description of DerivativeParams::is_identity
     */
    public function is_identity(array $in_size): bool
    {
        return $in_size[0] <= $this->sizing->ideal_size[0] && $in_size[1] <= $this->sizing->ideal_size[1];
    }

    public function will_watermark($out_size, ImageStandardParams $image_std_params): bool
    {
        if ($this->use_watermark) {
            $min_size = $image_std_params->getWatermark()->min_size;
            return $min_size[0] <= $out_size[0] || $min_size[1] <= $out_size[1];
        }

        return false;
    }

    /**
     * Formats a size name into a 2 chars identifier usable in filename.
     */
    public static function derivative_to_url(string $t): string
    {
        return substr($t, 0, 2);
    }

    /**
     * Formats a size array into a identifier usable in filename.
     */
    public static function size_to_url(array $s): string
    {
        if ($s[0] == $s[1]) {
            return (string) $s[0];
        }

        return $s[0] . 'x' . $s[1];
    }

    public static function size_equals(array $s1, array $s2): bool
    {
        return ($s1[0] == $s2[0] && $s1[1] == $s2[1]);
    }

    /**
     * Converts a char a-z into a float.
     */
    public static function char_to_fraction(string $c): float
    {
        return (ord($c) - ord('a')) / 25;
    }

    /**
     * Converts a float into a char a-z.
     */
    public static function fraction_to_char(float $f): string
    {
        return chr(ord('a') + (int) round($f * 25));
    }
}
