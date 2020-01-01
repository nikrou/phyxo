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

use Phyxo\Conf;
use Phyxo\Extension\Theme;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminTemplate extends Template
{
    public function __construct(TranslatorInterface $translator, RouterInterface $router, string $compileDir, string $adminThemeDir)
    {
        parent::__construct($translator, $router, $compileDir);
        $this->setTheme(new Theme($adminThemeDir, '.'), null);
    }
}
