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

// the final goal is to respect all PSR-2 rules.

$header = <<<'EOF'
    This file is part of Phyxo package

    Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
    Licensed under the GPL version 2.0 license.

    For the full copyright and license information, please view the LICENSE
    file that was distributed with this source code.
    EOF;

$finder = PhpCsFixer\Finder::create()->in(
    [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]
);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        'header_comment' => ['comment_type' => 'comment', 'header' => $header, 'location' => 'after_open', 'separate' => 'bottom'],
        '@Symfony' => true,
        'yoda_style' => false,
        'blank_line_after_opening_tag' => false,
        'no_leading_import_slash' => false,
        'global_namespace_import' => false,
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder);
