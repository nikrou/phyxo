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

namespace Phyxo\Template;

class Combinable
{
    /** @var string */
    public $id;
    /** @var string */
    public $path;
    /** @var string */
    public $version;
    /** @var bool */
    public $is_template;

    /**
     * @param string $id
     * @param string $path
     * @param string $version
     */
    public function __construct($id, $path, $version = 0)
    {
        $this->id = $id;
        $this->set_path($path);
        $this->version = $version;
        $this->is_template = false;
    }

    /**
     * @param string $path
     */
    public function set_path($path)
    {
        if (!empty($path)) {
            $this->path = $path;
        }
    }

    /**
     * @return bool
     */
    public function is_remote()
    {
        return \Phyxo\Functions\URL::url_is_remote($this->path) || strncmp($this->path, '//', 2) == 0;
    }
}
