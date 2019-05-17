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

class Theme extends Extension
{
    protected $id, $name, $template;

    public function __construct(string $root, string $id, string $template = 'template')
    {
        $this->root = $root;
        $this->id = $id;
        $this->template = $template;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getId()
    {
        return $this->id;
    }

    public function  getTemplate()
    {
        return $this->template;
    }
}
