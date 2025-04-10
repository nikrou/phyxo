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
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;

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

    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::SYMFONY_72,
        LevelSetList::UP_TO_PHP_84,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ])

    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ])

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        // naming: true,
        privatization: true,
        typeDeclarations: true
    );
