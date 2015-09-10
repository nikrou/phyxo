<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

class tabsheet
{
    public $sheets, $uniqid, $name, $titlename, $selected;

    /*
      $name is the tabsheet's name inside the template .tpl file
      $titlename in the template is affected by $titlename value
    */
    public function __construct($name = 'TABSHEET', $titlename = 'TABSHEET_TITLE') {
        $this->sheets = array();
        $this->uniqid = null;
        $this->name = $name;
        $this->titlename = $titlename;
        $this->selected = "";
    }

    public function set_id($id) {
        $this->uniqid = $id;
    }

    /*
      add a tab
    */
    public function add($name, $caption, $url, $selected = false) {
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

    /*
      remove a tab
    */
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

    /*
      select a tab to be active
    */
    public function select($name) {
        $this->sheets = trigger_change('tabsheet_before_select', $this->sheets, $this->uniqid);
        if (!array_key_exists($name, $this->sheets)) {
            $keys = array_keys($this->sheets);
            $name = $keys[0];
        }
        $this->selected = $name;
    }

    /*
      set $titlename value
    */
    public function set_titlename($titlename) {
        $this->titlename = $titlename;
        return $this->titlename;
    }

    /*
      returns $titlename value
    */
    public function get_titlename() {
        return $this->titlename;
    }

    /*
      returns properties of selected tab
    */
    public function get_selected() {
        if (!empty($this->selected)) {
            return $this->sheets[$this->selected];
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
    public function assign() {
        global $template;

        $template->set_filename('tabsheet', 'tabsheet.tpl');
        $template->assign('tabsheet', $this->sheets);
        $template->assign('tabsheet_selected', $this->selected);

        $selected_tab = $this->get_selected();

        if (isset($selected_tab)) {
            $template->assign(array($this->titlename => '['.$selected_tab['caption'].']'));
        }

        $template->assign_var_from_handle($this->name, 'tabsheet');
        $template->clear_assign('tabsheet');
    }
}
