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

use mageekguy\atoum;
use mageekguy\atoum\reports;

// Enable extension
//$extension = new reports\extension($script);
//$extension->addToRunner($runner);

// Write all on stdout.
$stdOutWriter = new atoum\writers\std\out();

// Generate a CLI report.
$cliReport = new atoum\reports\realtime\cli();
$cliReport->addWriter($stdOutWriter);

$runner->addTestsFromDirectory('tests/units/');
$runner->addReport($cliReport);
