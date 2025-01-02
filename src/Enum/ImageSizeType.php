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

namespace App\Enum;

enum ImageSizeType: string
{
    case ORIGINAL = 'original';
    case SQUARE = 'square';
    case THUMB = 'thumb';
    case XXSMALL = '2small';
    case XSMALL = 'xsmall';
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';
    case XLARGE = 'xlarge';
    case XXLARGE = 'xxlarge';
    case CUSTOM = 'custom';

    /**
     * @return static[]
     */
    public static function getAllTypes(): array
    {
        return [
            self::SQUARE, self::THUMB, self::XXSMALL, self::XSMALL, self::SMALL, self::MEDIUM, self::LARGE, self::XLARGE, self::XXLARGE
        ];
    }

    /**
     * @return string[]
     */
    public static function getAllTypesValues(): array
    {
        return array_map(fn ($case) => $case->value, self::getAllTypes());
    }

    /**
     * @return array<string, string>
     */
    public static function getAllPrefixes()
    {
        return array_map(fn ($case) => substr($case->value, 0, 2), self::getAllTypes());
    }

    public function toString(): string
    {
        return $this->value;
    }
}
