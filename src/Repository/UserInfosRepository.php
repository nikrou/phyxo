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

class UserInfosRepository extends BaseRepository
{
    public function findAll()
    {
        $query = 'SELECT user_id, nb_image_page, status, language, expand, show_nb_comments,';
        $query .= ' show_nb_hits, recent_period, theme, registration_date, enabled_high,';
        $query .= ' level, activation_key, activation_key_expire, lastmodified FROM ' . self::USER_INFOS_TABLE;

        return $this->conn->db_query($query);
    }

    public function findByTheme(string $theme)
    {
        $query = 'SELECT user_id, nb_image_page, status, language, expand, show_nb_comments,';
        $query .= ' show_nb_hits, recent_period, theme, registration_date, enabled_high,';
        $query .= ' level, activation_key, activation_key_expire, lastmodified FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE theme = \'' . $this->conn->db_real_escape_string($theme) . '\'';

        return $this->conn->db_query($query);
    }

    public function findByUserId(int $user_id)
    {
        $query = 'SELECT user_id, nb_image_page, status, language, expand, show_nb_comments,';
        $query .= ' show_nb_hits, recent_period, theme, registration_date, enabled_high,';
        $query .= ' level, activation_key, activation_key_expire, lastmodified FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function findByStatuses(array $statuses)
    {
        $query = 'SELECT user_id, nb_image_page, status, language, expand, show_nb_comments,';
        $query .= ' show_nb_hits, recent_period, theme, registration_date, enabled_high,';
        $query .= ' level, activation_key, activation_key_expire, lastmodified FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE status  ' . $this->conn->in($statuses);

        return $this->conn->db_query($query);
    }

    public function getDistinctUser()
    {
        $query = 'SELECT DISTINCT user_id FROM ' . self::USER_INFOS_TABLE;

        return $this->conn->db_query($query);
    }

    public function getNewUsers(? string $start = null, ? string $end = null, bool $count_only = false)
    {
        if ($count_only) {
            $query = 'SELECT count(1)';
        } else {
            $query = 'SELECT user_id';
        }

        $query .= ' FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE';

        if (!empty($start)) {
            $query .= ' registration_date > \'' . $this->conn->db_real_escape_string($start) . '\'';
        }

        if (!empty($end)) {
            if (!is_null($start)) {
                $query .= ' AND';
            }
            $query .= ' registration_date <= \'' . $this->conn->db_real_escape_string($end) . '\'';
        }

        if ($count_only) {
            list($nb_users) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_users;
        } else {
            return $this->conn->db_query($query);
        }
    }

    public function getCompleteUserInfos(int $user_id)
    {
        $query = 'SELECT ui.user_id, nb_image_page, status, language, expand, show_nb_comments,';
        $query .= ' show_nb_hits, recent_period, theme, registration_date, enabled_high,';
        $query .= ' level, activation_key, activation_key_expire, lastmodified,';
        $query .= ' t.name AS theme_name, uc.need_update, uc.cache_update_time, uc.forbidden_categories,';
        $query .= ' uc.nb_total_images, uc.last_photo_date, uc. nb_available_tags, uc.nb_available_comments,';
        $query .= ' uc. image_access_type, uc.image_access_list FROM ' . self::USER_INFOS_TABLE . '  as ui ';
        $query .= ' LEFT JOIN ' . self::USER_CACHE_TABLE . '  as uc ON ui . user_id = uc . user_id ';
        $query .= ' LEFT JOIN ' . self::THEMES_TABLE . '  as t ON t . id = ui . theme ';
        $query .= ' WHERE ui . user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function updateLanguageForLanguages(string $language, array $languages)
    {
        $query = 'UPDATE ' . self::USER_INFOS_TABLE;
        $query .= ' SET language = \'' . $this->conn->db_real_escape_string($language) . '\'';
        $query .= ' WHERE language ' . $this->conn->in($languages);
        return $this->conn->db_query($query);
    }

    public function updateUserInfos(array $datas, int $user_id)
    {
        $this->conn->single_update(self::USER_INFOS_TABLE, $datas, ['user_id' => $user_id]);
    }

    public function updateFieldForUsers(string $field, string $value, array $user_ids)
    {
        $query = 'UPDATE ' . self::USER_INFOS_TABLE;
        $query .= ' SET ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function updateFieldsForUsers(array $fields, array $user_ids)
    {
        $query = 'UPDATE ' . self::USER_INFOS_TABLE;
        $query .= ' SET ';

        $first = true;
        foreach ($fields as $field => $value) {
            if (!$first) {
                $query .= ', ';
            } else {
                $first = false;
            }
            $query .= $field . '=\'' . $this->conn->db_real_escape_string($value) . '\'';
        }

        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::USER_INFOS_TABLE, $fields, $datas);
    }

    public function massInserts(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::USER_INFOS_TABLE, $fields, $datas);
    }

    public function deleteByUserId(int $user_id)
    {
        $query = 'DELETE FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function deleteByUserIds(array $user_ids)
    {
        $query = 'DELETE FROM ' . self::USER_INFOS_TABLE;
        $query .= ' WHERE user_id  ' . $this->conn->in($user_ids);
        $this->conn->db_query($query);
    }

    public function getInfos(int $user_id)
    {
        $query = 'SELECT ui.*, uc.*, t.name AS theme_name FROM ' . self::USER_INFOS_TABLE . ' AS ui';
        $query .= ' LEFT JOIN ' . self::USER_CACHE_TABLE . ' AS uc ON ui.user_id = uc.user_id';
        $query .= ' LEFT JOIN ' . self::THEMES_TABLE . ' AS t ON t.id = ui.theme';
        $query .= ' WHERE ui.user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function getMaxLastModified()
    {
        $query = 'SELECT ' . $this->conn->db_date_to_ts('MAX(lastmodified)') . ', COUNT(1)';
        $query .= ' FROM ' . self::USER_INFOS_TABLE;

        return $this->conn->db_query($query);
    }
}
