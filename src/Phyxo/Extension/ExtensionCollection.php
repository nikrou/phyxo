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

use Traversable;

class ExtensionCollection
{
    /** @var array<string, mixed> */
    private array $extensions_by_class;

    /** @var array<string, string> */
    private array $extensions_by_name = [];

    /**
     * @param array<mixed> $extensions
     */
    public function __construct(iterable $extensions)
    {
        $extensions = $extensions instanceof Traversable ? iterator_to_array($extensions) : $extensions;

        foreach ($extensions as $extension) {
            $plugin_id = preg_replace('`Plugins\\\\([^\\\\]+)\\\\Command\\\\.+`', '\\1', (string) $extension::class);

            $this->extensions_by_class[$plugin_id][] = $extension->getName();
            $this->extensions_by_name[$extension->getName()] = $plugin_id;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getExtensionsByClass()
    {
        return $this->extensions_by_class;
    }

    /**
     * @return array<string, string>
     */
    public function getExtensionsByName()
    {
        return $this->extensions_by_name;
    }
}
