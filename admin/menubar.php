<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire        https://www.phyxo.net/ |
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

if (!defined('PHPWG_ROOT_PATH')) {
  die ("Hacking attempt!");
}

use Phyxo\Block\BlockManager;

function abs_fn_cmp($a, $b) {
    return abs($a)-abs($b);
}

function make_consecutive(&$orders, $step=50) {
    uasort( $orders, 'abs_fn_cmp' );
    $crt = 1;
    foreach( $orders as $id=>$pos) {
        $orders[$id] = $step * ($pos<0 ? -$crt : $crt);
        $crt++;
    }
}

$menu = new BlockManager('menubar');
$menu->load_registered_blocks();
$reg_blocks = $menu->get_registered_blocks();

$mb_conf = $conf['blk_'.$menu->get_id()];
if (is_string($mb_conf)) {
    $mb_conf = json_decode($mb_conf, true);
}

if (!is_array($mb_conf)) {
    $mb_conf = array();
}


foreach ($mb_conf as $id => $pos) {
    if (!isset($reg_blocks[$id])) {
        unset($mb_conf[$id]);
    }
}

if (isset($_POST['reset'])) {
    $mb_conf = array();
    conf_update_param('blk_'.$menu->get_id(), '');
}

$idx = 1;
foreach ($reg_blocks as $id => $block) {
    if (!isset($mb_conf[$id])) {
        $mb_conf[$id] = $idx*50;
    }
    $idx++;
}


if (isset($_POST['submit'])) {
    foreach ( $mb_conf as $id => $pos ) {
        $hide = isset($_POST['hide_'.$id]);
        $mb_conf[$id] = ($hide ? -1 : +1)*abs($pos);

        $pos = (int)@$_POST['pos_'.$id];
        if ($pos>0) {
            $mb_conf[$id] = $mb_conf[$id] > 0 ? $pos : -$pos;
        }
    }
    make_consecutive( $mb_conf );

    $mb_conf_db = $mb_conf;
    conf_update_param('blk_'.$menu->get_id(), json_encode($mb_conf_db));

    $page['infos'][] = l10n('Order of menubar items has been updated successfully.');
}

make_consecutive($mb_conf);

foreach ($mb_conf as $id => $pos ) {
    $template->append(
        'blocks',
        array(
            'pos' => $pos/5,
            'reg' => $reg_blocks[$id]
        )
    );
}

$action = get_root_url().'admin/index.php?page=menubar';
$template->assign(array('F_ACTION' => $action));

$template->set_filename('menubar_admin_content', 'menubar.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'menubar_admin_content');
