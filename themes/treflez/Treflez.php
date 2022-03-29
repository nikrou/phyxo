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

 namespace Themes\treflez;

use Phyxo\Conf;
use Phyxo\Extension\AbstractTheme;

 class Treflez extends AbstractTheme
 {
     public function __construct(Conf $conf)
     {
         $this->config = new Config($conf);
     }

     public function getConfig(): array
     {
         return $this->config->getConfig();
     }
 }
