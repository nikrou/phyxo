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

namespace Phyxo\Template;

use Phyxo\Extension\Theme;

class AdminTemplate extends Template
{
    public function __construct(array $options = [])
    {
        parent::__construct($options);
    }

    public static function initialize($compile_dir, string $theme)
    {
        $template = new self();
        $template->setCompileDir($compile_dir);
        $template->setTheme(new Theme($theme, '.', ''));

        return $template;
    }
}
