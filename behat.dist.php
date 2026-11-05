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

use App\Tests\Behat\ApiContext;
use App\Tests\Behat\DBContext;
use App\Tests\Behat\FeatureContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\GherkinOptions;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\Gherkin\GherkinCompatibilityMode;
use Behat\MinkExtension\Context\MinkContext;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\PHPUnitAssertionsExtension\BehatPHPUnitAssertionsExtension;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return (new Config())
    ->withProfile((new Profile('default', [
        'translation' => [
            'locale' => 'en',
        ],
    ]))
        ->withGherkinOptions(new GherkinOptions()->withCompatibilityMode(GherkinCompatibilityMode::GHERKIN_42))
        ->withExtension(new Extension(BehatPHPUnitAssertionsExtension::class))
        ->withExtension(new Extension(SymfonyExtension::class, [
            'bootstrap' => 'tests/bootstrap.php',
            'kernel' => [
                'environment' => 'test',
            ],
        ]))
        ->withExtension(new Extension(MinkExtension::class, [
            'base_url' => 'http://localhost:1080/',
            'show_auto' => true,
            // 'show_cmd' => 'google-chrome %s',
            'default_session' => 'symfony',
            'sessions' => [
                'symfony' => [
                    'symfony' => null,
                ],
            ],
        ]))
        ->withSuite((new Suite('web'))
            ->withContexts(
                FeatureContext::class,
                DBContext::class,
                MinkContext::class
            )
            ->withPaths('%paths.base%/features/web'))
        ->withSuite((new Suite('api'))
            ->withContexts(
                DBContext::class,
                ApiContext::class
            )
            ->withPaths('%paths.base%/features/api')));
