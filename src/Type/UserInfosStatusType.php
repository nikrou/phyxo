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

class UserInfosStatusType extends Type
{
    const USER_INFOS_STATUS = 'user_infos_status';
    const STATUS_WEBMASTER = 'webmaster';
    const STATUS_ADMIN = 'admin';
    const STATUS_NORMAL = 'normal';
    const STATUS_GUEST = 'guest';

    public function getName()
    {
        return self:: USER_INFOS_STATUS;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('webmaster','admin','normal','guest')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::STATUS_WEBMASTER, self::STATUS_ADMIN, self::STATUS_NORMAL, self::STATUS_GUEST])) {
            throw new \InvalidArgumentException("Invalid status");
        }

        return $value;
    }
}
