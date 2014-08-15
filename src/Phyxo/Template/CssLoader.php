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

namespace Phyxo\Template;

use Phyxo\Template\Css;
use Phyxo\Template\FileCombiner;

class CssLoader
{
    /** @param Css[] */
    private $registered_css;
    /** @param int used to keep declaration order */
    private $counter;

    public function __construct() {
        $this->clear();
    }

    public function clear() {
        $this->registered_css = array();
        $this->counter = 0;
    }

    /**
     * @return Combinable[] array of combined CSS.
     */
    public function get_css() {
        uasort($this->registered_css, array(__CLASS__, 'cmp_by_order'));
        $combiner = new FileCombiner('css', $this->registered_css);
        return $combiner->combine();
    }

    /**
     * Callback for CSS files sorting.
     */
    private static function cmp_by_order($a, $b) {
        return $a->order - $b->order;
    }

    /**
     * Adds a new file, if a file with the same $id already exsists, the one with
     * the higher $order or higher $version is kept.
     *
     * @param string $id
     * @param string $path
     * @param string $version
     * @param int $order
     * @param bool $is_template
     */
    public function add($id, $path, $version=0, $order=0, $is_template=false) {
        if (!isset($this->registered_css[$id])) {
            // costum order as an higher impact than declaration order
            $css = new Css($id, $path, $version, $order*1000+$this->counter);
            $css->is_template = $is_template;
            $this->registered_css[$id] = $css;
            $this->counter++;
        } else {
            $css = $this->registered_css[$id];
            if ($css->order<$order*1000 || version_compare($css->version, $version)<0) {
                unset($this->registered_css[$id]);
                $this->add($id, $path, $version, $order, $is_template);
            }
        }
    }
}
