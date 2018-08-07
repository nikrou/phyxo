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

namespace Phyxo\Cache;

/**
   Provides a persistent cache mechanism across multiple page loads/sessions etc...
 */
abstract class PersistentCache
{
    var $default_lifetime = 86400;
    protected $instance_key = PHPWG_VERSION;

    /**
       @return a key that can be safely be used with get/set methods
     */
    public function make_key($key)
    {
        if (is_array($key)) {
            $key = implode('&', $key);
        }
        $key .= $this->instance_key;
        return md5($key);
    }

    /**
       Searches for a key in the persistent cache and fills corresponding value.
       @param string $key
       @param out mixed $value
       @return false if the $key is not found in cache ($value is not modified in this case)
     */
    public abstract function get($key, &$value);

    /**
       Sets a key/value pair in the persistent cache.
       @param string $key - it should be the return value of make_key function
       @param mixed $value
       @param int $lifetime
       @return false on error
     */
    public abstract function set($key, $value, $lifetime = null);

    /**
       Purge the persistent cache.
       @param boolean $all - if false only expired items will be purged
     */
    public abstract function purge($all);
}
