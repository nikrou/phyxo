<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (!defined("GROUPS_BASE_URL")) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// |                            variables init                             |
// +-----------------------------------------------------------------------+

if (isset($_GET['group_id']) and is_numeric($_GET['group_id'])) {
    $page['group'] = $_GET['group_id'];

    // +-----------------------------------------------------------------------+
    // |                                updates                                |
    // +-----------------------------------------------------------------------+

    if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
        // if you forbid access to a category, all sub-categories become
        // automatically forbidden
        $subcats = get_subcat_ids($_POST['cat_true']);
        $query = 'DELETE FROM '.GROUP_ACCESS_TABLE;
        $query .= ' WHERE group_id = '.$page['group'].' AND cat_id '.$conn->in($subcats);
        $conn->db_query($query);
    } elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
        $uppercats = get_uppercat_ids($_POST['cat_false']);
        $private_uppercats = array();

        $query = 'SELECT id FROM '.CATEGORIES_TABLE;
        $query .= ' WHERE id '.$conn->in($uppercats).' AND status = \'private\';';
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            $private_uppercats[] = $row['id'];
        }

        // retrying to authorize a category which is already authorized may cause
        // an error (in SQL statement), so we need to know which categories are
        // accesible
        $authorized_ids = array();

        $query = 'SELECT cat_id FROM '.GROUP_ACCESS_TABLE.' WHERE group_id = '.$conn->db_real_escape_string($page['group']);
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            $authorized_ids[] = $row['cat_id'];
        }

        $inserts = array();
        $to_autorize_ids = array_diff($private_uppercats, $authorized_ids);
        foreach ($to_autorize_ids as $to_autorize_id) {
            $inserts[] = array(
                'group_id' => $page['group'],
                'cat_id' => $to_autorize_id
            );
        }

        $conn->mass_inserts(GROUP_ACCESS_TABLE, array('group_id','cat_id'), $inserts);
        invalidate_user_cache();
    }

    // +-----------------------------------------------------------------------+
    // |                             template init                             |
    // +-----------------------------------------------------------------------+

    $template->set_filenames(array('double_select' => 'double_select.tpl'));

    $template->assign(
        array(
            'TITLE' =>
            l10n(
                'Manage permissions for group "%s"',
                get_groupname($page['group'])
            ),
            'L_CAT_OPTIONS_TRUE'=>l10n('Authorized'),
            'L_CAT_OPTIONS_FALSE'=>l10n('Forbidden'),
            'F_ACTION' =>
            GROUPS_BASE_URL.'&amp;section=perm&amp;group_id='.$page['group']
        )
    );

    // only private categories are listed
    $query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_true .= ' LEFT JOIN '.GROUP_ACCESS_TABLE.' ON cat_id = id';
    $query_true .= ' WHERE status = \'private\' AND group_id = '.$conn->db_real_escape_string($page['group']);
    display_select_cat_wrapper($query_true, array(), 'category_option_true');

    $result = $conn->db_query($query_true);
    $authorized_ids = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $authorized_ids[] = $row['id'];
    }

    $query_false = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE.' WHERE status = \'private\'';
    if (count($authorized_ids) > 0) {
        $query_false.= ' AND id NOT '.$conn->in($authorized_ids);
    }
    display_select_cat_wrapper($query_false,array(),'category_option_false');

    // +-----------------------------------------------------------------------+
    // |                           html code display                           |
    // +-----------------------------------------------------------------------+

    $template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
} else {
    $query = 'SELECT id, name, is_default FROM '.GROUPS_TABLE.' ORDER BY name ASC';
    $result = $conn->db_query($query);

    $perm_url = GROUPS_BASE_URL.'&amp;section=perm&amp;group_id=';

    while ($row = $conn->db_fetch_assoc($result)) {
        $template->append(
            'groups',
            array(
                'NAME' => $row['name'],
                'ID' => $row['id'],
                'IS_DEFAULT' => ($conn->get_boolean($row['is_default']) ? ' ['.l10n('default').']' : ''),
                'U_PERM' => $perm_url.$row['id'],
            )
        );
    }
    $template->assign(array('TITLE' => l10n('Groups')));
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'groups');
