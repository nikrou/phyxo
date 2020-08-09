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

class UserCacheImageAccessTypeType extends Type
{
    const USER_CACHE_IMAGE_ACCESS_TYPE = 'user_cache_image_access_type';
    const ACCESS_TYPE_NOT_IN = 'NOT IN';
    const ACCESS_TYPE_IN = 'IN';

    public function getName()
    {
        return self::USER_CACHE_IMAGE_ACCESS_TYPE;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('NOT IN', 'IN')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::ACCESS_TYPE_NOT_IN, self::ACCESS_TYPE_IN])) {
            throw new \InvalidArgumentException("Invalid state");
        }

        return $value;
    }
}
