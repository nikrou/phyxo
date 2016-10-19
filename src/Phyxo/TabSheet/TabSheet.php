<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
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

namespace Phyxo\TabSheet;

use Phyxo\Template\Template;

class TabSheet
{
    private
        $sheets = array(), $uniq_id = null,
        $selected = '', $titlename= '', $name = '';

    public function __construct($name='TABSHEET', $titlename='TABSHEET_TITLE') {
        $this->name = $name;
        $this->titlename = $titlename;
    }

    public function add($name, $caption, $url, $selected=false) {
        if (!isset($this->sheets[$name])) {
            $this->sheets[$name] = array(
                'caption' => $caption,
                'url' => $url
            );

            if($selected) {
                $this->selected = $name;
            }

            return true;
        }

        return false;
    }

    public function delete($name) {
        if (isset($this->sheets[$name])) {
            array_splice($this->sheets, $name, 1);

            if ($this->selected == $name) {
                $this->selected = '';
            }

            return true;
        }

        return false;
    }

    public function select(&$name) {
        $this->sheets = trigger_change('tabsheet_before_select', $this->sheets, $this->uniq_id);
        if (empty($this->sheets[$name])) {
            $keys = array_keys($this->sheets);
            $name = $keys[0];
        }

        $this->selected = $name;
    }

    public function setId($id) {
        $this->uniq_id = $id;
    }

    public function setTitleName($title) {
        $this->titlename = $title;
    }

    public function getTitleName() {
        return $this->titlename;
    }

    public function getSelected() {
        if (!empty($this->selected)) {
            return $this->sheets[$this->selected];
        } else {
            return null;
        }
    }

    public function getCaption() {
        if (!empty($this->selected)) {
            return $this->sheets[$this->selected]['caption'];
        } else {
            return null;
        }

    }

    /*
     * Build TabSheet and assign this content to current page
     *
     * Fill $this->$name {default value = TABSHEET} with HTML code for tabsheet
     * Fill $this->titlename {default value = TABSHEET_TITLE} with formated caption of the selected tab
     */
    public function assign(\Phyxo\Template\Template $template) { // @TODO : move stuff to Template class
        $template->set_filename('tabsheet', 'tabsheet.tpl');
        $template->assign('tabsheet', $this->sheets);
        $template->assign('tabsheet_selected', $this->selected);

        $selected_tab = $this->getSelected();
        if (isset($selected_tab)) {
            $template->assign(array($this->titlename => '['.$selected_tab['caption'].']'));
        }

        $template->assign_var_from_handle($this->name, 'tabsheet');
        $template->clear_assign('tabsheet');
    }
}