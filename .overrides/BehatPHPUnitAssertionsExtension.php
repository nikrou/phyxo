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

namespace Behat\PHPUnitAssertionsExtension;

use Behat\Behat\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\Exception\ServiceContainer\ExceptionExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class BehatPHPUnitAssertionsExtension implements Extension
{
    public function getConfigKey(): string
    {
        return 'phpunit_assertions';
    }

    public function initialize(ExtensionManager $extensionManager): void
    {
        // No-op
    }

    public function configure(ArrayNodeDefinition $builder): void
    {
        // No-op
    }

    public function load(ContainerBuilder $container, array $config): void
    {
        $this->loadStringer($container);
        $this->loadBootstrappingListener($container);
    }

    private function loadStringer(ContainerBuilder $container): void
    {
        $definition = new Definition(PHPUnitExceptionStringer::class);
        $definition->addTag(ExceptionExtension::STRINGER_TAG, ['priority' => 50]);
        $container->setDefinition(ExceptionExtension::STRINGER_TAG . '.phpunit_assertions', $definition);
    }

    private function loadBootstrappingListener(ContainerBuilder $container): void
    {
        $definition = new Definition(PHPUnitBootstrappingListener::class);
        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, ['priority' => -10]);
        $container->setDefinition('phpunit_assertions.bootstrapping_listener', $definition);
    }

    public function process(ContainerBuilder $container): void
    {
        // No-op
    }
}
