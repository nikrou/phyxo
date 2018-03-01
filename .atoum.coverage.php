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
use mageekguy\atoum\reports;
use mageekguy\atoum\reports\coverage;
use mageekguy\atoum\writers\std;

$script->addDefaultReport();

$coverage = new coverage\html();
$coverage->addWriter(new std\out());
$coverage->setOutPutDirectory(__DIR__ . '/coverage/html');
$runner->addReport($coverage);
