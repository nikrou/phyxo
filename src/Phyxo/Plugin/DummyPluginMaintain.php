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

use Phyxo\Plugin\PluginMaintain;

class DummyPluginMaintain implements PluginMaintain
{
    protected $plugin_id;

    public function __construct(string $id)
    {
        $this->plugin_id = $id;
    }

    public function install(string $plugin_version): array
    {
        $errors = [];

        return $errors;
    }

    public function activate(string $plugin_version): array
    {
        $errors = [];

        return $errors;
    }

    public function deactivate()
    {
    }

    public function uninstall()
    {
    }

    public function update(string $old_version, string $new_version): array
    {
        $errors = [];

        return $errors;
    }
}
