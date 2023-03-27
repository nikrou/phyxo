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

namespace Phyxo\Extension;

class ExtensionCollection
{
    private $extensions_by_class,

    $extensions_by_name = [];

    public function __construct(iterable $extensions)
    {
        $extensions = $extensions instanceof \Traversable ? iterator_to_array($extensions) : $extensions;

        foreach ($extensions as $extension) {
            $plugin_id = preg_replace('`Plugins\\\\([^\\\\]+)\\\\Command\\\\.+`', '\\1', $extension::class);

            $this->extensions_by_class[$plugin_id][] = $extension->getName();
            $this->extensions_by_name[$extension->getName()] = $plugin_id;
        }
    }

    public function getExtensionsByClass()
    {
        return $this->extensions_by_class;
    }

    public function getExtensionsByName()
    {
        return $this->extensions_by_name;
    }
}
