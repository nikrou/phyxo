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

namespace App\Repository;

use Phyxo\DBLayer\DBLayer;

class BaseRepository
{
    const CATEGORIES_TABLE = 'phyxo_categories';
    const COMMENTS_TABLE = 'phyxo_comments';
    const CONFIG_TABLE = 'phyxo_config';
    const FAVORITES_TABLE = 'phyxo_favorites';
    const GROUP_ACCESS_TABLE = 'phyxo_group_access';
    const GROUPS_TABLE = 'phyxo_groups';
    const HISTORY_TABLE = 'phyxo_history';
    const HISTORY_SUMMARY_TABLE = 'phyxo_history_summary';
    const IMAGE_CATEGORY_TABLE = 'phyxo_image_category';
    const IMAGES_TABLE = 'phyxo_images';
    const SESSIONS_TABLE = 'phyxo_sessions';
    const SITES_TABLE = 'phyxo_sites';
    const USER_ACCESS_TABLE = 'phyxo_user_access';
    const USER_GROUP_TABLE = 'phyxo_user_group';
    const USERS_TABLE = 'phyxo_users';
    const USER_INFOS_TABLE = 'phyxo_user_infos';
    const USER_FEED_TABLE = 'phyxo_user_feed';
    const RATE_TABLE = 'phyxo_rate';
    const USER_CACHE_TABLE = 'phyxo_user_cache';
    const USER_CACHE_CATEGORIES_TABLE = 'phyxo_user_cache_categories';
    const CADDIE_TABLE = 'phyxo_caddie';
    const UPGRADE_TABLE = 'phyxo_upgrade';
    const SEARCH_TABLE = 'phyxo_search';
    const USER_MAIL_NOTIFICATION_TABLE = 'phyxo_user_mail_notification';
    const TAGS_TABLE = 'phyxo_tags';
    const IMAGE_TAG_TABLE = 'phyxo_image_tag';
    const PLUGINS_TABLE = 'phyxo_plugins';
    const OLD_PERMALINKS_TABLE = 'phyxo_old_permalinks';
    const THEMES_TABLE = 'phyxo_themes';
    const LANGUAGES_TABLE = 'phyxo_languages';

    protected $conn = null;

    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function addOrderByFields(string $order_by_string)
    {
        return str_ireplace(['order by', ' asc', ' desc'], ['', '', ''], $order_by_string);
    }

    /**
     * Compute sql WHERE condition with restrict and filter data.
     * "FandF" means Forbidden and Filters.
     *
     * @param array  $condition_fields    one witch fields apply each filter
     *                                    - forbidden_categories
     *                                    - visible_categories
     *                                    - forbidden_images
     *                                    - visible_images
     * @param string $prefix_condition    prefixes query if condition is not empty
     * @param bool   $force_one_condition use at least "1 = 1"
     *
     * @return string
     */
    public function getSQLConditionFandF(array $condition_fields, ? string $prefix_condition = null, bool $force_one_condition = false)
    {
        global $user, $filter;

        $sql_list = [];

        foreach ($condition_fields as $condition => $field_name) {
            switch ($condition) {
                case 'forbidden_categories':

                        if (!empty($user['forbidden_categories'])) {
                            $sql_list[] = $field_name . ' NOT IN (' . $user['forbidden_categories'] . ')';
                        }
                        break;

                case 'visible_categories':

                        if (!empty($filter['visible_categories'])) {
                            $sql_list[] = $field_name . ' IN (' . $filter['visible_categories'] . ')';
                        }
                        break;

                case 'visible_images':
                    if (!empty($filter['visible_images'])) {
                        $sql_list[] = $field_name . ' IN (' . $filter['visible_images'] . ')';
                    }
                // note there is no break - visible include forbidden
                // no break
                case 'forbidden_images':
                    if (!empty($user['image_access_list']) or $user['image_access_type'] != 'NOT IN') {
                        $table_prefix = null;
                        if ($field_name == 'id') {
                            $table_prefix = '';
                        } elseif ($field_name == 'i.id') {
                            $table_prefix = 'i.';
                        }
                        if (isset($table_prefix)) {
                            $sql_list[] = $table_prefix . 'level<=' . $user['level'];
                        } elseif (!empty($user['image_access_list']) and !empty($user['image_access_type'])) {
                            $sql_list[] = $field_name . ' ' . $user['image_access_type'] . ' (' . $user['image_access_list'] . ')';
                        }
                    }
                    break;
                default:

                        die('Unknown condition: ' . $condition);
                        break;
            }
        }

        if (count($sql_list) > 0) {
            $sql = '(' . implode(' AND ', $sql_list) . ')';
        } else {
            $sql = $force_one_condition ? '1 = 1' : '';
        }

        if (isset($prefix_condition) and !empty($sql)) {
            $sql = $prefix_condition . ' ' . $sql;
        }

        return $sql;
    }
}
