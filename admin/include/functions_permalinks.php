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


/** returns a category id that corresponds to the given permalink (or null)
 * @param string permalink
 */
function parse_sort_variables($sortable_by, $default_field, $get_param, $get_rejects, $template_var, $anchor = '')
{
    global $template;

    $url_components = parse_url($_SERVER['REQUEST_URI']);

    $base_url = $url_components['path'];

    parse_str($url_components['query'], $vars);
    $is_first = true;
    foreach ($vars as $key => $value) {
        if (!in_array($key, $get_rejects) and $key != $get_param) {
            $base_url .= $is_first ? '?' : '&amp;';
            $is_first = false;
            $base_url .= $key . '=' . urlencode($value);
        }
    }

    $ret = array();
    foreach ($sortable_by as $field) {
        $url = $base_url;
        $disp = '↓'; // @TODO: an small image is better

        if ($field !== @$_GET[$get_param]) {
            if (!isset($default_field) or $default_field != $field) { // the first should be the default
                $url = \Phyxo\Functions\URL::add_url_params($url, array($get_param => $field));
            } elseif (isset($default_field) and !isset($_GET[$get_param])) {
                $ret[] = $field;
                $disp = '<em>' . $disp . '</em>';
            }
        } else {
            $ret[] = $field;
            $disp = '<em>' . $disp . '</em>';
        }
        if (isset($template_var)) {
            $template->assign(
                $template_var . strtoupper($field),
                '<a href="' . $url . $anchor . '" title="' . \Phyxo\Functions\Language::l10n('Sort order') . '">' . $disp . '</a>'
            );
        }
    }
    return $ret;
}

function get_cat_id_from_permalink($permalink)
{
    global $conn;

    $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE permalink = \'' . $conn->db_real_escape_string($permalink) . '\'';
    $ids = $conn->query2array($query, null, 'id');
    if (!empty($ids)) {
        return $ids[0];
    }

    return null;
}

/** returns a category id that has used before this permalink (or null)
 * @param string permalink
 * @param boolean is_hit if true update the usage counters on the old permalinks
 */
function get_cat_id_from_old_permalink($permalink)
{
    global $conn;

    $query = 'SELECT c.id FROM ' . OLD_PERMALINKS_TABLE . ' AS op';
    $query .= ' LEFT JOIN ' . CATEGORIES_TABLE . ' AS c ON op.cat_id=c.id';
    $query .= ' WHERE op.permalink=\'' . $conn->db_real_escape_string($permalink) . '\'';
    $query .= ' LIMIT 1';
    $result = $conn->db_query($query);
    $cat_id = null;
    if ($conn->db_num_rows($result)) {
        list($cat_id) = $conn->db_fetch_row($result);
    }

    return $cat_id;
}


/** deletes the permalink associated with a category
 * returns true on success
 * @param int cat_id the target category id
 * @param boolean save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function delete_cat_permalink($cat_id, $save)
{
    global $page, $cache, $conn;

    $query = 'SELECT permalink FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id=\'' . $conn->db_real_escape_string($cat_id) . '\'';
    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result)) {
        list($permalink) = $conn->db_fetch_row($result);
    }
    if (!isset($permalink)) { // no permalink; nothing to do
        return true;
    }

    if ($save) {
        $old_cat_id = get_cat_id_from_old_permalink($permalink);
        if (isset($old_cat_id) and $old_cat_id != $cat_id) {
            $page['errors'][] = sprintf(
                \Phyxo\Functions\Language::l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
                $permalink,
                $old_cat_id
            );
            return false;
        }
    }

    $query = 'UPDATE ' . CATEGORIES_TABLE;
    $query .= ' SET permalink=NULL';
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($cat_id);
    $conn->db_query($query);

    unset($cache['cat_names']); //force regeneration
    if ($save) {
        if (isset($old_cat_id)) {
            $query = 'UPDATE ' . OLD_PERMALINKS_TABLE;
            $query .= ' SET date_deleted=NOW()';
            $query .= ' WHERE cat_id=' . $conn->db_real_escape_string($cat_id);
            $query .= ' AND permalink=\'' . $conn->db_real_escape_string($permalink) . '\'';
        } else {
            $query = 'INSERT INTO ' . OLD_PERMALINKS_TABLE;
            $query .= ' (permalink, cat_id, date_deleted) VALUES';
            $query .= '( \'' . $conn->db_real_escape_string($permalink) . '\',' . $conn->db_real_escape_string($cat_id) . ',NOW())';
        }
        $conn->db_query($query);
    }

    return true;
}

/** sets a new permalink for a category
 * returns true on success
 * @param int cat_id the target category id
 * @param string permalink the new permalink
 * @param boolean save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function set_cat_permalink($cat_id, $permalink, $save)
{
    global $page, $cache, $conn;

    $sanitized_permalink = preg_replace('#[^a-zA-Z0-9_/-]#', '', $permalink);
    $sanitized_permalink = trim($sanitized_permalink, '/');
    $sanitized_permalink = str_replace('//', '/', $sanitized_permalink);
    if ($sanitized_permalink != $permalink or preg_match('#^(\d)+(-.*)?$#', $permalink)) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('The permalink name must be composed of a-z, A-Z, 0-9, "-", "_" or "/". It must not be numeric or start with number followed by "-"');
        return false;
    }

    // check if the new permalink is actively used
    $existing_cat_id = get_cat_id_from_permalink($permalink);
    if (isset($existing_cat_id)) {
        if ($existing_cat_id == $cat_id) { // no change required
            return true;
        } else {
            $page['errors'][] = sprintf(
                \Phyxo\Functions\Language::l10n('Permalink %s is already used by album %s'),
                $permalink,
                $existing_cat_id
            );
            return false;
        }
    }

    // check if the new permalink was historically used
    $old_cat_id = get_cat_id_from_old_permalink($permalink);
    if (isset($old_cat_id) and $old_cat_id != $cat_id) {
        $page['errors'][] = sprintf(
            \Phyxo\Functions\Language::l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
            $permalink,
            $old_cat_id
        );
        return false;
    }

    if (!delete_cat_permalink($cat_id, $save)) {
        return false;
    }

    if (isset($old_cat_id)) { // the new permalink must not be active and old at the same time
        assert($old_cat_id == $cat_id); // @TODO: remove !
        $query = 'DELETE FROM ' . OLD_PERMALINKS_TABLE;
        $query .= ' WHERE cat_id=' . $conn->db_real_escape_string($old_cat_id);
        $query .= ' AND permalink=\'' . $conn->db_real_escape_string($permalink) . '\'';
        $conn->db_query($query);
    }

    $query = 'UPDATE ' . CATEGORIES_TABLE;
    $query .= ' SET permalink=\'' . $conn->db_real_escape_string($permalink) . '\'';
    $query .= ' WHERE id=' . $conn->db_real_escape_string($cat_id);
    $conn->db_query($query);

    unset($cache['cat_names']); //force regeneration

    return true;
}
