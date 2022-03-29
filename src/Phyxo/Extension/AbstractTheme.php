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

namespace Phyxo\Extension;

abstract class AbstractTheme
{
    private const CLASSNAME_FORMAT = '\\Themes\\%s\\%s';

    public static function getClassName(string $theme_id): string
    {
        return sprintf(self::CLASSNAME_FORMAT, $theme_id, ucfirst($theme_id));
    }

    abstract function getConfig(): array;
}
