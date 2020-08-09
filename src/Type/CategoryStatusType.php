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

class CategoryStatusType extends Type
{
    const CATEGORY_STATUS = 'categories_status';
    const STATUS_PRIVATE = 'private';
    const STATUS_PUBLIC = 'public';

    public function getName()
    {
        return self:: CATEGORY_STATUS;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('private', 'public')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::STATUS_PRIVATE, self::STATUS_PUBLIC])) {
            throw new \InvalidArgumentException("Invalid status");
        }

        return $value;
    }
}
