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

namespace Phyxo\Plugin;

class DummyPluginMaintain implements PluginMaintain
{
    public function __construct(protected string $plugin_id)
    {
    }

    public function install(string $plugin_version): array
    {
        return [];
    }

    public function activate(string $plugin_version): array
    {
        return [];
    }

    public function deactivate()
    {
    }

    public function uninstall()
    {
    }

    public function update(string $old_version, string $new_version): array
    {
        return [];
    }
}
