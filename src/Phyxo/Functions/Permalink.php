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

namespace Phyxo\Functions;

use App\Repository\OldPermalinkRepository;
use App\Repository\CategoryRepository;

class Permalink
{
    /** returns a category id that corresponds to the given permalink (or null)
     * @param string permalink
     */
    public static function parse_sort_variables($sortable_by, $default_field, $get_param, $get_rejects, $template_var, $anchor = '')
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

        $ret = [];
        foreach ($sortable_by as $field) {
            $url = $base_url;
            $disp = '↓'; // @TODO: an small image is better

            if ($field !== @$_GET[$get_param]) {
                if (!isset($default_field) or $default_field != $field) { // the first should be the default
                    $url = \Phyxo\Functions\URL::add_url_params($url, [$get_param => $field]);
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

    public static function get_cat_id_from_permalink($permalink)
    {
        global $conn;

        $result = (new CategoryRepository($conn))->findByField('permalink', $permalink);
        $ids = $conn->result2array($result, null, 'id');
        if (!empty($ids)) {
            return $ids[0];
        }

        return null;
    }

    /** deletes the permalink associated with a category
     * returns true on success
     * @param int cat_id the target category id
     * @param boolean save if true, the current category-permalink association
     * is saved in the old permalinks table in case external links hit it
     */
    public static function delete_cat_permalink($cat_id, $save)
    {
        global $page, $cache, $conn;

        $result = (new CategoryRepository($conn))->findById($cat_id);
        if ($conn->db_num_rows($result)) {
            list($permalink) = $conn->db_fetch_row($result);
        }
        if (!isset($permalink)) { // no permalink; nothing to do
            return true;
        }

        if ($save) {
            $old_cat_id = (new OldPermalinkRepository($conn))->getCategoryIdFromOldPermalink($permalink);
            if (isset($old_cat_id) and $old_cat_id != $cat_id) {
                $page['errors'][] = sprintf(
                    \Phyxo\Functions\Language::l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
                    $permalink,
                    $old_cat_id
                );
                return false;
            }
        }

        (new CategoryRepository($conn))->updateCategory(['permalink' => null], $cat_id);

        unset($cache['cat_names']); //force regeneration
        if ($save) {
            if (isset($old_cat_id)) {
                (new OldPermalinkRepository($conn))->updateOldPermalink(['date_deleted' => 'now()'], ['cat_id' => $cat_id, 'permalink' => $permalink]);
            } else {
                (new OldPermalinkRepository($conn))->addOldPermalink([
                    'permalink' => $permalink,
                    'cat_id' => $cat_id,
                    'date_deleted' => 'now()'
                ]);
            }
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
    public static function set_cat_permalink($cat_id, $permalink, $save)
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
        $existing_cat_id = (new OldPermalinkRepository($conn))->getCategoryIdFromOldPermalink($permalink);
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
        $old_cat_id = (new OldPermalinkRepository($conn))->getCategoryIdFromOldPermalink($permalink);
        if (isset($old_cat_id) and $old_cat_id != $cat_id) {
            $page['errors'][] = sprintf(
                \Phyxo\Functions\Language::l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
                $permalink,
                $old_cat_id
            );
            return false;
        }

        if (!self::delete_cat_permalink($cat_id, $save)) {
            return false;
        }

        if (isset($old_cat_id)) { // the new permalink must not be active and old at the same time
            (new OldPermalinkRepository($conn))->deleteByCatIdAndPermalink($old_cat_id, $permalink);
        }

        (new CategoryRepository($conn))->updateCategory(['permalink' => $permalink], $cat_id);

        unset($cache['cat_names']); //force regeneration

        return true;
    }
}
