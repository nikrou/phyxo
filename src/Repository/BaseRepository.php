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

use Phyxo\DBLayer\iDBLayer;
use Phyxo\Functions\Language;
use Symfony\Component\Security\Core\User\UserInterface;

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
    protected $sql_recent_date;

    public function __construct(iDBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function addOrderByFields(string $order_by_string)
    {
        return str_ireplace(['order by', ' asc', ' desc'], ['', '', ''], $order_by_string);
    }

    public function getNow()
    {
        list($dbnow) = $this->conn->db_fetch_row($this->conn->db_query('SELECT NOW();'));

        return $dbnow;
    }

    /**
     * Compute sql WHERE condition with restrict and filter data.
     * "FandF" means Forbidden and Filters.
     *
     * @param array  $UserConditions     array with keys forbidden_categories, image_access_list and image_access_type
     * @param array  $filter
     * @param array  $condition_fields    one witch fields apply each filter
     *                                    - forbidden_categories
     *                                    - visible_categories
     *                                    - forbidden_images
     *                                    - visible_images
     * @param string $prefix_condition    prefixes query if condition is not empty
     * @param bool   $force_one_condition use at least "1 = 1"
     */
    public function getSQLConditionFandF(UserInterface $user, array $filter = [], $condition_fields, ? string $prefix_condition = null, bool $force_one_condition = false): string
    {
        $sql_list = [];

        foreach ($condition_fields as $condition => $field_name) {
            switch ($condition) {
                case 'forbidden_categories':

                        if ($user->getForbiddenCategories()) {
                            $sql_list[] = $field_name . ' NOT ' . $this->conn->in($user->getForbiddenCategories());
                        }
                        break;

                case 'visible_categories':

                        if (!empty($filter['visible_categories'])) {
                            $sql_list[] = $field_name . ' ' . $this->conn->in($filter['visible_categories']);
                        }
                        break;

                case 'visible_images':
                    if (!empty($filter['visible_images'])) {
                        $sql_list[] = $field_name . ' ' . $this->conn->in($filter['visible_images']);
                    }
                // note there is no break - visible include forbidden no break
                case 'forbidden_images':
                    if (count($user->getImageAccessList()) > 0 || $user->getImageAccessType() !== 'NOT IN') {
                        $table_prefix = null;
                        if ($field_name === 'id') {
                            $table_prefix = '';
                        } elseif ($field_name === 'i.id') {
                            $table_prefix = 'i.';
                        }
                        if (isset($table_prefix)) {
                            $sql_list[] = $table_prefix . 'level<=' . $user->getLevel();
                        } elseif (count($user->getImageAccessList()) > 0 && !empty($user->getImageAccessType())) {
                            if ($user->getImageAccessType() === 'IN') {
                                $sql_list[] = $field_name . ' ' . $this->conn->in($user->getImageAccessList());
                            } elseif ($user->getImageAccessType() === 'NOT IN') {
                                $sql_list[] = $field_name . ' NOT ' . $this->conn->in($user->getImageAccessList());
                            }
                        }
                    }
                    break;
            }
        }

        if (count($sql_list) > 0) {
            $sql = '(' . implode(' AND ', $sql_list) . ')';
        } else {
            $sql = $force_one_condition ? '1 = 1' : '';
        }

        if (isset($prefix_condition) && !empty($sql)) {
            $sql = $prefix_condition . ' ' . $sql;
        }

        return $sql;
    }

    /**
     * Returns sql WHERE condition for recent photos/albums for current user.
     * @param array $UserConditions with keys recent_period ans last_photo_date
     */
    public function getRecentPhotos(UserInterface $user, string $db_field): string
    {
        if (!$user->getLastPhotoDate()) {
            return '0=1';
        }

        return $db_field . '>=LEAST('
            . $this->conn->db_get_recent_period_expression($user->getRecentPeriod())
            . ',' . $this->conn->db_get_recent_period_expression(1, $user->getLastPhotoDate()) . ')';
    }

    /**
     * Get standard sql where in order to restrict and filter categories and images.
     * IMAGE_CATEGORY_TABLE must be named "ic" in the query
     */
    public function getStandardSQLWhereRestrictFilter(UserInterface $user, array $filter = [], string $prefix_condition, string $img_field = 'ic.image_id', bool $force_one_condition = false): string
    {
        return $this->getSQLConditionFandF(
            $user,
            $filter,
            [
                'forbidden_categories' => 'ic.category_id',
                'visible_categories' => 'ic.category_id',
                'visible_images' => $img_field
            ],
            $prefix_condition,
            $force_one_condition
        );
    }

    public function getIcon(string $date, UserInterface $user, bool $is_child_date = false): array
    {
        if (empty($date)) {
            return [];
        }

        if (empty($this->sql_recent_date)) {
            $this->sql_recent_date = $this->conn->db_get_recent_period($user->getRecentPeriod());
        }

        $icon = [
            'IS_CHILD_DATE' => $is_child_date,
            'sql_recent_date' => $this->sql_recent_date
        ];

        return ($date > $icon['sql_recent_date']) ? $icon : [];
    }
}
