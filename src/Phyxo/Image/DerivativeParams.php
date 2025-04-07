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

    // used for non-custom images to regenerate the cached files
    public int $last_mod_time = 0;
    public bool $use_watermark = false;

    public function __construct(private readonly SizingParams $sizing)
    {
    }

    public function __sleep(): array
    {
        return ['last_mod_time', 'sizing'];
    }

    /**
     * Adds tokens depending on sizing configuration.
     *
     * @param array<string> $tokens
     */
    public function addUrlTokens(array &$tokens): void
    {
        $this->sizing->addUrlTokens($tokens);
    }

    /**
     * @param array{0:int, 1:int} $in_size
     *
     * @return array{0:int, 1:int}
     */
    public function computeFinalSize(array $in_size): array
    {
        $crop_rect = null;
        $scale_size = [];
        $this->sizing->compute($in_size, $crop_rect, $scale_size);

        return $scale_size !== [] ? $scale_size : $in_size;
    }

    public function maxWidth(): int
    {
        return $this->sizing->getIdealSize()[0];
    }

    public function maxHeight(): int
    {
        return $this->sizing->getIdealSize()[1];
    }

    /**
     * @param array{0:int, 1:int} $in_size
     */
    public function isIdentity(array $in_size): bool
    {
        return $in_size[0] <= $this->sizing->getIdealSize()[0] && $in_size[1] <= $this->sizing->getIdealSize()[1];
    }

    /**
     * @param array{0:int, 1:int} $out_size
     */
    public function willWatermark(array $out_size, ImageStandardParams $image_std_params): bool
    {
        if ($this->use_watermark) {
            $min_size = $image_std_params->getWatermark()->getMinSize();

            return $min_size[0] <= $out_size[0] || $min_size[1] <= $out_size[1];
        }

        return false;
    }

    /**
     * Formats a size name into a 2 chars identifier usable in filename.
     */
    public static function derivativeToUrl(string $t): string
    {
        return substr($t, 0, 2);
    }

    /**
     * Formats a size array into a identifier usable in filename.
     *
     * @param array{0:int, 1:int} $s
     */
    public static function sizeToUrl(array $s): string
    {
        if ($s[0] === $s[1]) {
            return (string) $s[0];
        }

        return $s[0] . 'x' . $s[1];
    }

    /**
     * @param array{0:int, 1:int} $s1
     * @param array{0:int, 1:int} $s2
     */
    public static function sizeEquals(array $s1, array $s2): bool
    {
        return $s1[0] === $s2[0] && $s1[1] === $s2[1];
    }

    /**
     * Converts a char a-z into a float.
     */
    public static function charToFraction(string $c): float
    {
        return (ord($c) - ord('a')) / 25;
    }

    /**
     * Converts a float into a char a-z.
     */
    public static function fractionToChar(float $f): string
    {
        return chr(ord('a') + (int) round($f * 25));
    }

    public function getSizing(): SizingParams
    {
        return $this->sizing;
    }
}
