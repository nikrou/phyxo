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

namespace Phyxo\Block;

use Phyxo\Block\DisplayBlock;

/**
 * Manages a set of RegisteredBlock and DisplayBlock.
 */
class BlockManager
{
    /** @var string */
    protected $id;
    /** @var RegisteredBlock[] */
    protected $registered_blocks = array();
    /** @var DisplayBlock[] */
    protected $display_blocks = array();

    /**
     * @param string $id
     */
    public function __construct($id) {
        $this->id = $id;
    }

    /**
     * Triggers a notice that allows plugins of menu blocks to register the blocks.
     */
    public function load_registered_blocks() {
        trigger_notify('blockmanager_register_blocks', array($this));
    }

    /**
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * @return RegisteredBlock[]
     */
    public function get_registered_blocks() {
        return $this->registered_blocks;
    }

    /**
     * Add a block with the menu. Usually called in 'blockmanager_register_blocks' event.
     *
     * @param RegisteredBlock $block
     */
    public function register_block($block) {
        if (isset($this->registered_blocks[$block->get_id()])) {
            return false;
        }
        $this->registered_blocks[$block->get_id()] = $block;

        return true;
    }

    /**
     * Performs one time preparation of registered blocks for display.
     * Triggers 'blockmanager_prepare_display' event where plugins can
     * reposition or hide blocks
     */
    public function prepare_display() {
        global $conf;

        $conf_id = 'blk_'.$this->id;
        $mb_conf = isset($conf[$conf_id]) ? $conf[$conf_id] : array();
        if (!is_array($mb_conf)) {
            // @TODO: remove serialize/unserialize
            $mb_conf = @unserialize($mb_conf);
        }

        $idx = 1;
        foreach ($this->registered_blocks as $id => $block) {
            $pos = isset($mb_conf[$id]) ? $mb_conf[$id] : $idx*50;
            if ($pos>0) {
                $this->display_blocks[$id] = new DisplayBlock($block);
                $this->display_blocks[$id]->set_position($pos);
            }
            $idx++;
        }
        $this->sort_blocks();
        trigger_notify('blockmanager_prepare_display', array($this));
        $this->sort_blocks();
    }

    /**
     * Returns true if the block is hidden.
     *
     * @param string $block_id
     * @return bool
     */
    public function is_hidden($block_id) {
        return !isset($this->display_blocks[$block_id]);
    }

    /**
     * Remove a block from the displayed blocks.
     *
     * @param string $block_id
     */
    public function hide_block($block_id) {
        unset($this->display_blocks[$block_id]);
    }

    /**
     * Returns a visible block.
     *
     * @param string $block_id
     * @return DisplayBlock|null
     */
    public function get_block($block_id) {
        if (isset($this->display_blocks[$block_id])) {
            return $this->display_blocks[$block_id];
        }

        return null;
    }

    /**
     * Changes the position of a block.
     *
     * @param string $block_id
     * @param int $position
     */
    public function set_block_position($block_id, $position) {
        if (isset($this->display_blocks[$block_id])) {
            $this->display_blocks[$block_id]->set_position($position);
        }
    }

    /**
     * Sorts the blocks.
     */
    protected function sort_blocks() {
        uasort($this->display_blocks, array(__CLASS__, 'cmp_by_position'));
    }

    /**
     * Callback for blocks sorting.
     */
    protected static function cmp_by_position($a, $b) {
        return $a->get_position() - $b->get_position();
    }

    /**
     * Parse the menu and assign the result in a template variable.
     *
     * @param string $var
     * @param string $file
     */
    public function apply($var, $file) {
        global $template;

        $template->set_filename('menubar', $file);
        trigger_notify('blockmanager_apply', array($this) );

        foreach ($this->display_blocks as $id => $block) {
            if (empty($block->raw_content) && empty($block->template)) {
                $this->hide_block($id);
            }
        }
        $this->sort_blocks();
        $template->assign('blocks', $this->display_blocks);
        $template->assign_var_from_handle($var, 'menubar');
    }
}
