<?php

use \mageekguy\atoum;

// Write all on stdout.
$stdOutWriter = new atoum\writers\std\out();

// Generate a CLI report.
$cliReport = new atoum\reports\realtime\cli();
$cliReport->addWriter($stdOutWriter);

// Coverage
$coverageField = new atoum\report\fields\runner\coverage\html('Phyxo', '/var/www/coverage/phyxo');
$coverageField->setRootUrl('http://localhost/coverage/phyxo');
$cliReport->addField($coverageField);

$runner->addReport($cliReport);
