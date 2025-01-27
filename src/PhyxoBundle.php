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

namespace App;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PhyxoBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @phpstan-ignore-next-line */
        $definition->rootNode()
            ->children()
            ->arrayNode('templates')
            ->info('List of mandatory templates for public theme')
            ->children()
            // user
            ->stringNode('profile')->defaultValue('profile')->info('page profile')->end()
            ->stringNode('login')->defaultValue('identification')->end()
            ->stringNode('register')->defaultValue('register')->end()
            ->stringNode('reset_password')->defaultValue('reset_password')->end()
            ->stringNode('forgot_password')->defaultValue('forgot_password')->end()
            ->stringNode('reset_password')->defaultValue('reset_password')->end()
            // menubar fragment
            ->stringNode('menubar')->defaultValue('_menubar')->info('included template for navbar')->end()
            // album & picture
            ->stringNode('albums')->defaultValue('albums')->end()
            ->stringNode('album')->defaultValue('album')->end()
            ->stringNode('picture')->defaultValue('picture')->end()
            ->stringNode('tags')->defaultValue('tags')->end()
            // search
            ->stringNode('search')->defaultValue('search')->end()
            ->stringNode('search_results')->defaultValue('search_results')->end()
            ->stringNode('search_rules')->defaultValue('search_rules')->end()
            // comments
            ->stringNode('comments')->defaultValue('comments')->end()
            // calendar
            ->stringNode('calendar')->defaultValue('calendar')->end()
            ->stringNode('calendar_by_year')->defaultValue('calendar_by_year')->end()
            ->stringNode('calendar_by_month')->defaultValue('calendar_by_month')->end()
            ->stringNode('calendar_by_day')->defaultValue('calendar_by_day')->end()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phyxo.templates', $config['templates']);
    }
}
