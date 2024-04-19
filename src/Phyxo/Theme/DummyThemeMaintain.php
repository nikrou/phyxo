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

namespace Phyxo\Theme;

class DummyThemeMaintain implements ThemeMaintain
{
    public function __construct(
        /** @phpstan-ignore-next-line */
        private readonly string $theme_id
    ) {
    }

    public function activate($theme_version): array
    {
        return [];
    }

    public function deactivate()
    {
    }

    public function delete()
    {
    }
}
