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

enum UserPrivacyLevelType: int implements TranslatableInterface
{
    case DEFAULT = 0;
    case CONTACT = 1;
    case FRIENDS = 2;
    case FAMILY = 4;
    case ADMINS = 8;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans(sprintf('Level %d', $this->value), locale: $locale);
    }
}
