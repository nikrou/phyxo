<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

namespace Phyxo\DBLayer;

class dbException extends \Exception
{
    private $query = '';

    public function __construct($message, $code=0, \Exception $previous=null) {
        parent::__construct($message, $code, $previous);
    }

    public function __set($name, $value) {
        if ($name!='query') {
            return;
        }
        $this->query = $value;
    }

    public function __toString() {
        $res = __CLASS__ . ": [{$this->code}]: {$this->message}\n";
        if (!empty($this->query)) {
            $res .= 'Query: '.$this->query;
        }

        return $res;
    }
}