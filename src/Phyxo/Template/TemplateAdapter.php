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

class TemplateAdapter
{
    /**
     * @deprecated use "translate" modifier
     */
    public function l10n($text) {
        return \l10n($text);
    }

    /**
     * @deprecated use "translate_dec" modifier
     */
    public function l10n_dec($s, $p, $v) {
        return \l10n_dec($s, $p, $v);
    }

    /**
     * @deprecated use "translate" or "sprintf" modifier
     */
    public function sprintf() {
        $args = func_get_args();
        return call_user_func_array('sprintf',  $args );
    }

    /**
     * @param string $type
     * @param array $img
     * @return DerivativeImage
     */
    public function derivative($type, $img) {
        return new \DerivativeImage($type, $img);
    }

    /**
     * @param string $type
     * @param array $img
     * @return string
     */
    public function derivative_url($type, $img) {
        return \DerivativeImage::url($type, $img);
    }
}
