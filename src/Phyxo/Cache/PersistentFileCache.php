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

use Phyxo\Cache\PersistentCache;

/**
   Implementation of a persistent cache using files.
 */
class PersistentFileCache extends PersistentCache
{
    private $dir;

    public function __construct()
    {
        global $conf;
        $this->dir = PHPWG_ROOT_PATH . $conf['data_location'] . 'cache/';
    }

    public function get($key, &$value)
    {
        $loaded = @file_get_contents($this->dir . $key . '.cache');
        if ($loaded !== false && ($loaded = unserialize($loaded)) !== false) { // @TODO: use json_encode
            if ($loaded['expire'] > time()) {
                $value = $loaded['data'];
                return true;
            }
        }
        return false;
    }

    public function set($key, $value, $lifetime = null)
    {
        if ($lifetime === null) {
            $lifetime = $this->default_lifetime;
        }

        if (rand() % 97 == 0) {
            $this->purge(false);
        }

        // @TODO: use json_encode
        $serialized = serialize(array(
            'expire' => time() + $lifetime,
            'data' => $value
        ));

        if (false === @file_put_contents($this->dir . $key . '.cache', $serialized)) {
            mkgetdir($this->dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR);
            if (false === @file_put_contents($this->dir . $key . '.cache', $serialized)) {
                return false;
            }
        }

        return true;
    }

    public function purge($all)
    {
        $files = glob($this->dir . '*.cache');
        if (empty($files)) {
            return;
        }

        $limit = time() - $this->default_lifetime;
        foreach ($files as $file) {
            if ($all || @filemtime($file) < $limit)
                @unlink($file);
        }
    }
}

