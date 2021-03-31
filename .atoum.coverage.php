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

use atoum\atoum;

$coverageHtmlField = new atoum\report\fields\runner\coverage\html('Phyxo', __DIR__ . '/coverage/html');

$script
  ->addDefaultReport()
  ->addField($coverageHtmlField);
