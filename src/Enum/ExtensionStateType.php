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

enum ExtensionStateType: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case MISSING = 'missing';
    case OBSOLETE = 'obsolete';
}
