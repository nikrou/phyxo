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

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum UserStatusType: string implements TranslatableInterface
{
    case WEBMASTER = 'webmaster';
    case ADMIN = 'admin';
    case NORMAL = 'normal';
    case GUEST = 'guest';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(sprintf('user_status_%s', $this->value), locale: $locale);
    }
}
