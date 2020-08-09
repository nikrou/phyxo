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

namespace App\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class HistoryImageTypeType extends Type
{
    const HISTORY_IMAGE_TYPE = 'history_image_type';
    const IMAGE_TYPE_PICTURE = 'picture';
    const IMAGE_TYPE_HIGH = 'high';
    const IMAGE_TYPE_OTHER = 'other';

    public function getName()
    {
        return self::HISTORY_IMAGE_TYPE;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('picture','high','other')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::IMAGE_TYPE_PICTURE, self::IMAGE_TYPE_HIGH, self::IMAGE_TYPE_OTHER])) {
            throw new \InvalidArgumentException("Invalid status");
        }

        return $value;
    }
}
