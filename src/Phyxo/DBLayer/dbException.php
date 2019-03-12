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

namespace Phyxo\DBLayer;

class dbException extends \Exception
{
    private $query = '';

    public function __construct($message, $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function __set($name, $value) {
        if ($name != 'query') {
            return;
        }
        $this->query = $value;
    }

    public function __toString() {
        $res = __CLASS__ . ": [{$this->code}]: {$this->message}\n";
        if (!empty($this->query)) {
            $res .= 'Query: ' . $this->query;
        }

        return $res;
    }
}
