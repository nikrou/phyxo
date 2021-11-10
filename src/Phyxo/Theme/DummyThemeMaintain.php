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

use Phyxo\Theme\ThemeMaintain;

class DummyThemeMaintain implements ThemeMaintain
{
    /** @phpstan-ignore-next-line */
    private $theme_id;

    public function __construct(string $id)
    {
        $this->theme_id = $id;
    }

    public function activate($theme_version): array
    {
        $errors = [];

        return $errors;
    }

    public function deactivate()
    {
    }

    public function delete()
    {
    }
}
