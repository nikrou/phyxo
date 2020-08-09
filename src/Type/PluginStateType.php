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

class PluginStateType extends Type
{
    const PLUGIN_STATE = 'plugin_state';
    const STATE_ACTIVE = 'active';
    const STATE_INACTIVE = 'inactive';

    public function getName()
    {
        return self::PLUGIN_STATE;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return "ENUM('active', 'inactive')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, [self::STATE_ACTIVE, self::STATE_INACTIVE])) {
            throw new \InvalidArgumentException("Invalid state");
        }

        return $value;
    }
}
