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

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\If_\IfToNullCoalescingAssignRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

    ->withSkipPath(__DIR__ . '/src/Phyxo/Functions/Ws/')

    ->withImportNames()

    ->withAttributesSets()

    ->withPhpSets()

    ->withComposerBased(twig: true, doctrine: true, phpunit: true)

    ->withSkip([
        IfToNullCoalescingAssignRector::class,
    ])

    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
