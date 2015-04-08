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

/**
 * @package functions\user
 */


/**
 * Checks if an email is well formed and not already in use.
 *
 * @param int $user_id
 * @param string $mail_address
 * @return string|void error message or nothing
 */
function validate_mail_address($user_id, $mail_address) {
    global $conf, $conn;

    if (empty($mail_address)
        and !($conf['obligatory_user_mail_address'] and in_array(script_basename(), array('register', 'profile')))) {
        return '';
    }

    if (!email_check_format($mail_address)) {
        return l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
    }

    if (defined("PHPWG_INSTALLED") and !empty($mail_address)) {
        $query = 'SELECT count(1) FROM '.USERS_TABLE;
        $query .= ' WHERE upper('.$conf['user_fields']['email'].') = upper(\''.$mail_address.'\')';
        $query .= (is_numeric($user_id) ? 'AND '.$conf['user_fields']['id'].' != \''.$user_id.'\'' : '').';';
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count != 0) {
            return l10n('this email address is already in use');
        }
    }
}

/**
 * Checks if a login is not already in use.
 * Comparision is case insensitive.
 *
 * @param string $login
 * @return string|void error message or nothing
 */
function validate_login_case($login) {
    global $conf, $conn;

    if (defined("PHPWG_INSTALLED")) {
        $query = 'SELECT '.$conf['user_fields']['username'].' FROM '.USERS_TABLE;
        $query .= ' WHERE LOWER('.$conn->db_real_escape_string($conf['user_fields']['username']).') = \''.strtolower($login).'\'';
        $count = $conn->db_num_rows($conn->db_query($query));

        if ($count > 0) {
            return l10n('this login is already used');
        }
    }
}
/**
 * Searches for user with the same username in different case.
 *
 * @param string $username typically typed in by user for identification
 * @return string $username found in database
 */
function search_case_username($username) {
    global $conf, $conn;

    $username_lo = strtolower($username);

    $users = array();

    $q = $conn->db_query('SELECT '.$conf['user_fields']['username'].' AS username FROM '.USERS_TABLE);
    while ($r = $conn->db_fetch_assoc($q)) {
        $users[$r['username']] = strtolower($r['username']);
    }
   // $users is now an associative table where the key is the account as
   // registered in the DB, and the value is this same account, in lower case

    $users_found = array_keys($users, $username_lo);
    // $users_found is now a table of which the values are all the accounts
    // which can be written in lowercase the same way as $username
    if (count($users_found) != 1) { // If ambiguous, don't allow lowercase writing
        return $username; // but normal writing will work
    } else {
        return $users_found[0];
    }
}

/**
 * Creates a new user.
 *
 * @param string $login
 * @param string $password
 * @param string $mail_adress
 * @param bool $notify_admin
 * @param array &$errors populated with error messages
 * @param bool $notify_user
 * @return int|false user id or false
 */
function register_user($login, $password, $mail_address, $notify_admin=true, &$errors = array(), $notify_user=false) {
    global $conf, $conn;

    if ($login == '') {
        $errors[] = l10n('Please, enter a login');
    }
    if (preg_match('/^.* $/', $login)) {
        $errors[] = l10n('login mustn\'t end with a space character');
    }
    if (preg_match('/^ .*$/', $login)) {
        $errors[] = l10n('login mustn\'t start with a space character');
    }
    if (get_userid($login)) {
        $errors[] = l10n('this login is already used');
    }
    if ($login != strip_tags($login)) {
        $errors[] = l10n('html tags are not allowed in login');
    }
    $mail_error = validate_mail_address(null, $mail_address);
    if ('' != $mail_error) {
        $errors[] = $mail_error;
    }

    if ($conf['insensitive_case_logon'] == true) {
        $login_error = validate_login_case($login);
        if ($login_error != '') {
            $errors[] = $login_error;
        }
    }

    $errors = trigger_change(
        'register_user_check',
        $errors,
        array(
            'username'=>$login,
            'password'=>$password,
            'email'=>$mail_address,
        )
    );

    // if no error until here, registration of the user
    if (count($errors) == 0) {
        $insert = array(
            $conf['user_fields']['username'] => $conn->db_real_escape_string($login),
            $conf['user_fields']['password'] => $conf['password_hash']($password),
            $conf['user_fields']['email'] => $mail_address
        );

        $conn->single_insert(USERS_TABLE, $insert);
        $user_id = $conn->db_insert_id(USERS_TABLE);

        // Assign by default groups
        $query = 'SELECT id FROM '.GROUPS_TABLE;
        $query .= ' WHERE is_default = \''.$conn->boolean_to_db(true).'\' ORDER BY id ASC;';
        $result = $conn->db_query($query);

        $inserts = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $inserts[] = array(
                'user_id' => $user_id,
                'group_id' => $row['id']
            );
        }

        if (count($inserts) != 0) {
            $conn->mass_inserts(USER_GROUP_TABLE, array('user_id', 'group_id'), $inserts);
        }

        $override = null;
        if ($notify_admin and $conf['browser_language']) {
            if (!get_browser_language($override['language'])) {
                $override=null;
            }
        }
        create_user_infos($user_id, $override);

        if ($notify_admin and $conf['email_admin_on_new_user']) {
            include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
            $admin_url = get_absolute_root_url().'admin.php?page=user_list&username='.$login;

            $keyargs_content = array(
                get_l10n_args('User: %s', stripslashes($login) ),
                get_l10n_args('Email: %s', $mail_address),
                get_l10n_args(''),
                get_l10n_args('Admin: %s', $admin_url),
            );

            pwg_mail_notification_admins(
                get_l10n_args('Registration of %s', stripslashes($login) ),
                $keyargs_content
            );
        }

        if ($notify_user and email_check_format($mail_address)) {
            include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

            $keyargs_content = array(
                get_l10n_args('Hello %s,', stripslashes($login)),
                get_l10n_args('Thank you for registering at %s!', $conf['gallery_title']),
                get_l10n_args('', ''),
                get_l10n_args('Here are your connection settings', ''),
                get_l10n_args('Username: %s', stripslashes($login)),
                get_l10n_args('Password: %s', stripslashes($password)),
                get_l10n_args('Email: %s', $mail_address),
                get_l10n_args('', ''),
                get_l10n_args('If you think you\'ve received this email in error, please contact us at %s', get_webmaster_mail_address()),
            );

            pwg_mail(
                $mail_address,
                array(
                    'subject' => '['.$conf['gallery_title'].'] '.l10n('Registration'),
                    'content' => l10n_args($keyargs_content),
                    'content_format' => 'text/plain',
                )
            );
        }

        trigger_notify(
            'register_user',
            array(
                'id' => $user_id,
                'username' => $login,
                'email' => $mail_address,
            )
        );

        return $user_id;
    } else {
        return false;
    }
}

/**
 * Fetches user data from database.
 * Same that getuserdata() but with additional tests for guest.
 *
 * @param int $user_id
 * @param boolean $user_cache
 * @return array
 */
function build_user($user_id, $use_cache=true) {
    global $conf;

    $user['id'] = $user_id;
    $user = array_merge( $user, getuserdata($user_id, $use_cache) );

    if ($user['id'] == $conf['guest_id'] and $user['status'] != 'guest') {
        $user['status'] = 'guest';
        $user['internal_status']['guest_must_be_guest'] = true;
    }

    // Check user theme
    if (!isset($user['theme_name'])) {
        $user['theme'] = get_default_theme();
    }

    return $user;
}

/**
 * Finds informations related to the user identifier.
 *
 * @param int $user_id
 * @param boolean $use_cache
 * @return array
 */
function getuserdata($user_id, $use_cache=false) {
    global $conf, $conn;

    // retrieve basic user data
    $query = 'SELECT ';
    $is_first = true;
    foreach ($conf['user_fields'] as $pwgfield => $dbfield) {
        if ($is_first) {
            $is_first = false;
        } else {
            $query .= ', ';
        }
        $query .= $dbfield.' AS '.$pwgfield;
  }
    $query.= ' FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['id'].' = \''.$user_id.'\'';

    $row = $conn->db_fetch_assoc($conn->db_query($query));

    // retrieve additional user data ?
    if ($conf['external_authentification']) {
        $query = 'SELECT COUNT(1) AS counter FROM '.USER_INFOS_TABLE.' AS ui';
        $query .= ' LEFT JOIN '.USER_CACHE_TABLE.' AS uc ON ui.user_id = uc.user_id';
        $query .= ' LEFT JOIN '.THEMES_TABLE.' AS t ON t.id = ui.theme';
        $query .= ' WHERE ui.user_id = '.$user_id;
        $query .= ' GROUP BY ui.user_id;';
        list($counter) = $conn->db_fetch_row($conn->db_query($query));
        if ($counter != 1) {
            create_user_infos($user_id);
        }
    }

    // retrieve user info
    $query = 'SELECT ui.*, uc.*, t.name AS theme_name FROM '.USER_INFOS_TABLE.' AS ui';
    $query .= ' LEFT JOIN '.USER_CACHE_TABLE.' AS uc ON ui.user_id = uc.user_id';
    $query .= ' LEFT JOIN '.THEMES_TABLE.' AS t ON t.id = ui.theme';
    $query .= ' WHERE ui.user_id = '.$user_id.';';

    $result = $conn->db_query($query);
    $user_infos_row = $conn->db_fetch_assoc($result);

    // then merge basic + additional user data
    $userdata = array_merge($row, $user_infos_row);

    foreach ($userdata as &$value) {
        // If the field is true or false, the variable is transformed into a boolean value.
        if ($conn->is_boolean($value)) {
            $value = $conn->get_boolean($value);
        }
    }
    unset($value);

    if ($use_cache) {
        if (!isset($userdata['need_update']) or !is_bool($userdata['need_update']) or $userdata['need_update'] == true) {
            $userdata['cache_update_time'] = time();

            // Set need update are done
            $userdata['need_update'] = false;

            $userdata['forbidden_categories'] = calculate_permissions($userdata['id'], $userdata['status']);

            /* now we build the list of forbidden images (this list does not contain
             * images that are not in at least an authorized category)
             */
            $query = 'SELECT DISTINCT(id) FROM '.IMAGES_TABLE;
            $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id=image_id';
            $query .= ' WHERE category_id NOT IN ('.$userdata['forbidden_categories'].') AND level>'.$userdata['level'];
            $forbidden_ids = $conn->query2array($query, null, 'id');

            if (empty($forbidden_ids)) {
                $forbidden_ids[] = 0;
            }
            $userdata['image_access_type'] = 'NOT IN'; //TODO maybe later
            $userdata['image_access_list'] = implode(',',$forbidden_ids);

            $query = 'SELECT COUNT(DISTINCT(image_id)) as total FROM '.IMAGE_CATEGORY_TABLE;
            $query .= ' WHERE category_id NOT IN ('.$userdata['forbidden_categories'].')';
            $query .= ' AND image_id '.$userdata['image_access_type'].' ('.$userdata['image_access_list'].')';
            list($userdata['nb_total_images']) = $conn->db_fetch_row($conn->db_query($query));

            // now we update user cache categories
            $user_cache_cats = get_computed_categories($userdata, null);
            if (!is_admin($userdata['status'])) { // for non admins we forbid categories with no image (feature 1053)
                $forbidden_ids = array();
                foreach ($user_cache_cats as $cat) {
                    if ($cat['count_images']==0) {
                        $forbidden_ids[] = $cat['cat_id'];
                        remove_computed_category($user_cache_cats, $cat);
                    }
                }
                if (!empty($forbidden_ids)) {
                    if (empty($userdata['forbidden_categories'])) {
                        $userdata['forbidden_categories'] = implode(',', $forbidden_ids);
                    } else {
                        $userdata['forbidden_categories'] .= ','.implode(',', $forbidden_ids);
                    }
                }
            }

            // delete user cache
            $conn->db_write_lock(USER_CACHE_CATEGORIES_TABLE);
            $query = 'DELETE FROM '.USER_CACHE_CATEGORIES_TABLE.' WHERE user_id = '.$userdata['id'];
            $conn->db_query($query);
            $conn->mass_inserts(
                USER_CACHE_CATEGORIES_TABLE,
                array(
                    'user_id', 'cat_id',
                    'date_last', 'max_date_last', 'nb_images', 'count_images', 'nb_categories', 'count_categories'
                ),
                $user_cache_cats,
                array('ignore' => true)
            );
            $conn->db_unlock();

            // update user cache
            $conn->db_start_transaction();
            try {
                $query = 'DELETE FROM '.USER_CACHE_TABLE.' WHERE user_id = '.$userdata['id'];
                $conn->db_query($query);

                $query = 'INSERT INTO '.USER_CACHE_TABLE;
                $query .= ' (user_id, need_update, cache_update_time, forbidden_categories, nb_total_images,';
                $query .= ' last_photo_date,image_access_type, image_access_list)';
                $query .= ' VALUES('.$userdata['id'].',\''.$conn->boolean_to_db($userdata['need_update']).'\',';
                $query .= $userdata['cache_update_time'].',\''.$userdata['forbidden_categories'].'\','.$userdata['nb_total_images'].',';
                $query .= (empty($userdata['last_photo_date']) ? 'NULL': '\''.$userdata['last_photo_date'].'\'');
                $query .= ',\''.$userdata['image_access_type'].'\',\''.$userdata['image_access_list'].'\')';
                $conn->db_query($query);
                $conn->db_commit();
            } catch (Exception $e) {
                $conn->db_rollback();
            }
        }
    }

    return $userdata;
}

/**
 * Deletes favorites of the current user if he's not allowed to see them.
 */
function check_user_favorites() {
    global $user, $conn;

    if ($user['forbidden_categories'] == '') {
        return;
    }

    // $filter['visible_categories'] and $filter['visible_images']
    // must be not used because filter <> restriction
    // retrieving images allowed : belonging to at least one authorized
    // category
    $query = 'SELECT DISTINCT f.image_id FROM '.FAVORITES_TABLE.' AS f';
    $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON f.image_id = ic.image_id';
    $query .= ' WHERE f.user_id = '.$user['id'];
    $query .= ' '.get_sql_condition_FandF(array('forbidden_categories' => 'ic.category_id'), ' AND ');
    $authorizeds = $conn->query2array($query, null, 'image_id');

    $query = 'SELECT image_id FROM '.FAVORITES_TABLE;
    $query .= ' WHERE user_id = '.$user['id'];
    $favorites = $conn->query2array($query, null, 'image_id');

    $to_deletes = array_diff($favorites, $authorizeds);
    if (count($to_deletes) > 0) {
        $query = 'DELETE FROM '.FAVORITES_TABLE;
        $query .= ' WHERE image_id '.$conn->in($to_deletes).' AND user_id = '.$user['id'];
        $conn->db_query($query);
    }
}

/**
 * Calculates the list of forbidden categories for a given user.
 *
 * Calculation is based on private categories minus categories authorized to
 * the groups the user belongs to minus the categories directly authorized
 * to the user. The list contains at least 0 to be compliant with queries
 * such as "WHERE category_id NOT IN ($forbidden_categories)"
 *
 * @param int $user_id
 * @param string $user_status
 * @return string comma separated ids
 */
function calculate_permissions($user_id, $user_status) {
    global $conn;

    $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE status = \'private\';';
    $private_array = $conn->query2array($query,null, 'id');

    // retrieve category ids directly authorized to the user
    $query = 'SELECT cat_id FROM '.USER_ACCESS_TABLE.' WHERE user_id = '.$user_id.';';
    $authorized_array = $conn->query2array($query,null, 'cat_id');

  // retrieve category ids authorized to the groups the user belongs to
    $query = 'SELECT cat_id FROM '.USER_GROUP_TABLE.' AS ug';
    $query .= ' LEFT JOIN '.GROUP_ACCESS_TABLE.' AS ga ON ug.group_id = ga.group_id';
    $query .= ' WHERE ug.user_id = '.$user_id.';';
    $authorized_array =
        array_merge(
            $authorized_array,
            $conn->query2array($query,null, 'cat_id')
        );

    // uniquify ids : some private categories might be authorized for the
    // groups and for the user
    $authorized_array = array_unique($authorized_array);

    // only unauthorized private categories are forbidden
    $forbidden_array = array_diff($private_array, $authorized_array);

    // if user is not an admin, locked categories are forbidden
    if (!is_admin($user_status)) {
        $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE visible = \''.$conn->boolean_to_db(false).'\'';
        $forbidden_array = array_merge($forbidden_array, $conn->query2array($query, null, 'id') );
        $forbidden_array = array_unique($forbidden_array);
    }

    if (empty($forbidden_array)) { // at least, the list contains 0 value. This category does not exists so
        // where clauses such as "WHERE category_id NOT IN(0)" will always be true.
        $forbidden_array[] = 0;
    }

    return implode(',', $forbidden_array);
}

/**
 * Returns user identifier thanks to his name.
 *
 * @param string $username
 * @param int|false
 */
function get_userid($username) {
    global $conf, $conn;

    $query = 'SELECT '.$conf['user_fields']['id'].' FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['username'].' = \''.$conn->db_real_escape_string($username).'\';';
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return false;
    } else {
        list($user_id) = $conn->db_fetch_row($result);
        return $user_id;
    }
}

/**
 * Returns user identifier thanks to his email.
 *
 * @param string $email
 * @param int|false
 */
function get_userid_by_email($email) {
    global $conf, $conn;

    $query = 'SELECT '.$conf['user_fields']['id'].' FROM '.USERS_TABLE;
    $query .= ' WHERE UPPER('.$conf['user_fields']['email'].') = UPPER(\''.$conn->db_real_escape_string($email).'\');';
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return false;
    } else {
        list($user_id) = $conn->db_fetch_row($result);
        return $user_id;
    }
}

/**
 * Returns a array with default user valuees.
 *
 * @param convert_str ceonferts 'true' and 'false' into booleans
 * @return array
 */
function get_default_user_info($convert_str=true) {
    global $cache, $conf, $conn;

    if (!isset($cache['default_user'])) {
        $query = 'SELECT * FROM '.USER_INFOS_TABLE.' WHERE user_id = '.$conf['default_user_id'].';';
        $result = $conn->db_query($query);

        if ($conn->db_num_rows($result) > 0) {
            $cache['default_user'] = $conn->db_fetch_assoc($result);

            unset($cache['default_user']['user_id']);
            unset($cache['default_user']['status']);
            unset($cache['default_user']['registration_date']);
        } else {
            $cache['default_user'] = false;
        }
    }

    if (is_array($cache['default_user']) and $convert_str) {
        $default_user = $cache['default_user'];
        foreach ($default_user as &$value) {
            // If the field is true or false, the variable is transformed into a boolean value.
            if ($conn->is_boolean($value)) {
                $value = $conn->get_boolean($value);
            }
        }
        return $default_user;
    } else {
        return $cache['default_user'];
    }
}

/**
 * Returns a default user value.
 *
 * @param string $value_name
 * @param mixed $default
 * @return mixed
 */
function get_default_user_value($value_name, $default) {
    $default_user = get_default_user_info(true);
    if ($default_user === false or empty($default_user[$value_name])) {
        return $default;
    } else {
        return $default_user[$value_name];
    }
}

/**
 * Returns the default theme.
 * If the default theme is not available it returns the first available one.
 *
 * @return string
 */
function get_default_theme() {
    $theme = get_default_user_value('theme', PHPWG_DEFAULT_TEMPLATE);
    if (check_theme_installed($theme)) {
        return $theme;
    }

    // let's find the first available theme
    $active_themes = array_keys(get_pwg_themes());
    return $active_themes[0];
}

/**
 * Returns the default language.
 *
 * @return string
 */
function get_default_language() {
    return get_default_user_value('language', PHPWG_DEFAULT_LANGUAGE);
}

/**
 * Tries to find the browser language among available languages.
 * @todo : try to match 'fr_CA' before 'fr'
 *
 * @param string &$lang
 * @return bool
 */
function get_browser_language(&$lang) {
    $browser_language = substr(@$_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
    foreach (get_languages() as $language_code => $language_name) {
        if (substr($language_code, 0, 2) == $browser_language) {
            $lang = $language_code;
            return true;
        }
    }

    return false;
}

/**
 * Creates user informations based on default values.
 *
 * @param int|int[] $user_ids
 * @param array $override_values values used to override default user values
 */
function create_user_infos($user_ids, $override_values=null) {
    global $conf, $conn;

    if (!is_array($user_ids)) {
        $user_ids = array($user_ids);
    }

    if (!empty($user_ids)) {
        $inserts = array();
        list($dbnow) = $conn->db_fetch_row($conn->db_query('SELECT NOW();'));

        $default_user = get_default_user_info(false);
        if ($default_user === false) {
            // Default on structure are used
            $default_user = array();
        }

        if (!is_null($override_values)) {
            $default_user = array_merge($default_user, $override_values);
        }

        foreach ($user_ids as $user_id) {
            $level= isset($default_user['level']) ? $default_user['level'] : 0;
            if ($user_id == $conf['webmaster_id']) {
                $status = 'webmaster';
                $level = max($conf['available_permission_levels']);
            } elseif (($user_id == $conf['guest_id']) or ($user_id == $conf['default_user_id'])) {
                $status = 'guest';
            } else {
                $status = 'normal';
            }

            $insert = array_merge(
                $default_user,
                array(
                    'user_id' => $user_id,
                    'status' => $status,
                    'registration_date' => $dbnow,
                    'level' => $level
                ));

            $inserts[] = $insert;
        }

        $conn->mass_inserts(USER_INFOS_TABLE, array_keys($inserts[0]), $inserts);
    }
}

/**
 * Returns the auto login key for an user or false if the user is not found.
 *
 * @param int $user_id
 * @param int $time
 * @param string &$username fille with corresponding username
 * @return string|false
 */
function calculate_auto_login_key($user_id, $time, &$username) {
    global $conf, $conn;

    $query = 'SELECT '.$conf['user_fields']['username'].' AS username';
    $query .= ', '.$conf['user_fields']['password'].' AS password FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['id'].' = '.$user_id;

    $result = $conn->db_query($query);
    if ($conn->db_num_rows($result) > 0) {
        $row = $conn->db_fetch_assoc($result);
        $data = $time.$user_id.$row['username'];
        $key = base64_encode(hash_hmac('sha1', $data, $conf['secret_key'].$row['password'],true));
        return $key;
    }

    return false;
}

/**
 * Performs all required actions for user login.
 *
 * @param int $user_id
 * @param bool $remember_me
 */
function log_user($user_id, $remember_me) {
    global $conf, $user;

    if ($remember_me and $conf['authorize_remembering']) {
        $now = time();
        $key = calculate_auto_login_key($user_id, $now, $username);
        if ($key!==false) {
            $cookie = $user_id.'-'.$now.'-'.$key;
            setcookie($conf['remember_me_name'],
            $cookie,
            time()+$conf['remember_me_length'],
            cookie_path(),ini_get('session.cookie_domain'),ini_get('session.cookie_secure'),
            ini_get('session.cookie_httponly')
            );
        }
    } else { // make sure we clean any remember me ...
        setcookie($conf['remember_me_name'], '', 0, cookie_path(),ini_get('session.cookie_domain'));
    }
    if (session_id()!="") { // we regenerate the session for security reasons
        // see http://www.acros.si/papers/session_fixation.pdf
        session_regenerate_id(true);
    } else {
        session_start();
    }
    $_SESSION['pwg_uid'] = (int)$user_id;
    $user['id'] = $_SESSION['pwg_uid'];
    trigger_notify('user_login', $user['id']);
}

/**
 * Performs auto-connection when cookie remember_me exists.
 *
 * @return bool
 */
function auto_login() {
    global $conf;

    if (isset( $_COOKIE[$conf['remember_me_name']])) {
        $cookie = explode('-', stripslashes($_COOKIE[$conf['remember_me_name']]));
        if (count($cookie)===3
        and is_numeric(@$cookie[0]) /*user id*/
        and is_numeric(@$cookie[1]) /*time*/
        and time()-$conf['remember_me_length']<=@$cookie[1]
        and time()>=@$cookie[1] /*cookie generated in the past*/ ) {
            $key = calculate_auto_login_key( $cookie[0], $cookie[1], $username );
            if ($key!==false and $key===$cookie[2]) {
                log_user($cookie[0], true);
                trigger_notify('login_success', stripslashes($username));
                return true;
            }
        }
        setcookie($conf['remember_me_name'], '', 0, cookie_path(),ini_get('session.cookie_domain'));
    }

    return false;
}

/**
 * Hashes a password
 *
 * @param string $password plain text
 * @return string
 */
function pwg_password_hash($password) {
    // From time to time algorithm need to be changed and password need to be rehashed
    // @See password_needs_rehash
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifies a password
 * If the hash is 'old' using PasswordHash class used in phyxo < 1.2, the hash is updated in database.
 *
 * @param string $password plain text
 * @param string $hash may be phpass hashed password
 * @param integer $user_id only useful to update password hash from phpass ones
 * @return bool
 */
function pwg_password_verify($password, $hash, $user_id=null) {
    global $conn;

    if (empty($hash) || strpos($hash, '$P') !== false || $hash == md5($password)) {
        $hash = pwg_password_hash($password);
        $conn->single_update(
            USERS_TABLE,
            array('password' => $hash),
            array('id' => $user_id)
        );
    }

    return password_verify($password, $hash);
}

/**
 * Tries to login a user given username and password.
 *
 * @param string $username
 * @param string $password
 * @param bool $remember_me
 * @return bool
 */
function try_log_user($username, $password, $remember_me) {
    return trigger_change('try_log_user', false, $username, $password, $remember_me);
}

add_event_handler('try_log_user', 'pwg_login');

/**
 * Default method for user login, can be overwritten with 'try_log_user' trigger.
 * @see try_log_user()
 *
 * @param string $username
 * @param string $password
 * @param bool $remember_me
 * @return bool
 */
function pwg_login($success, $username, $password, $remember_me) {
    global $conf, $conn;

    if ($success===true) {
        return true;
    }

    // we force the session table to be clean
    pwg_session_gc();

    // retrieving the encrypted password of the login submitted
    $query = 'SELECT '.$conf['user_fields']['id'].' AS id,';
    $query .= $conf['user_fields']['password'].' AS password';
    $query .= ' FROM '.USERS_TABLE;
    $query .= ' WHERE '.$conf['user_fields']['username'].' = \''.$conn->db_real_escape_string($username).'\';';
    $row = $conn->db_fetch_assoc($conn->db_query($query));
    if ($conf['password_verify']($password, $row['password'], $row['id'])) {
        log_user($row['id'], $remember_me);
        trigger_notify('login_success', stripslashes($username));
        return true;
    }
    trigger_notify('login_failure', stripslashes($username));

    return false;
}

/**
 * Performs all the cleanup on user logout.
 */
function logout_user() {
    global $conf;

    trigger_notify('user_logout', @$_SESSION['pwg_uid']);

    $_SESSION = array();
    session_unset();
    session_destroy();
    setcookie(session_name(),'',0, ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
    setcookie($conf['remember_me_name'], '', 0, cookie_path(),ini_get('session.cookie_domain'));
}

/**
 * Return user status.
 *
 * @param string $user_status used if $user not initialized
 * @return string
 */
function get_user_status($user_status='') {
    global $user;

    if (empty($user_status)) {
        if (isset($user['status'])) {
            $user_status = $user['status'];
        } else {
            // swicth to default value
            $user_status = '';
        }
    }

    return $user_status;
}

/**
 * Return ACCESS_* value for a given $status.
 *
 * @param string $user_status used if $user not initialized
 * @return int one of ACCESS_* constants
 */
function get_access_type_status($user_status='') {
    global $conf;

    switch (get_user_status($user_status))
        {
        case 'guest': {
            $access_type_status = ($conf['guest_access'] ? ACCESS_GUEST : ACCESS_FREE);
            break;
        }
        case 'generic': {
            $access_type_status = ACCESS_GUEST;
            break;
        }
        case 'normal': {
            $access_type_status = ACCESS_CLASSIC;
            break;
        }
        case 'admin': {
            $access_type_status = ACCESS_ADMINISTRATOR;
            break;
        }
        case 'webmaster': {
            $access_type_status = ACCESS_WEBMASTER;
            break;
        }
        default: {
            $access_type_status = ACCESS_FREE;
            break;
        }
        }

    return $access_type_status;
}

/**
 * Returns if user has access to a particular ACCESS_*
 *
 * @return int $access_type one of ACCESS_* constants
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_autorize_status($access_type, $user_status='') {
    return (get_access_type_status($user_status) >= $access_type);
}

/**
 * Abord script if user has no access to a particular ACCESS_*
 *
 * @return int $access_type one of ACCESS_* constants
 * @param string $user_status used if $user not initialized
 */
function check_status($access_type, $user_status='') {
    if (!is_autorize_status($access_type, $user_status)) {
        access_denied();
    }
}

/**
 * Returns if user is generic.
 *
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_generic($user_status='') {
    return get_user_status($user_status) == 'generic';
}

/**
 * Returns if user is a guest.
 *
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_a_guest($user_status='') {
    return get_user_status($user_status) == 'guest';
}

/**
 * Returns if user is, at least, a classic user.
 *
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_classic_user($user_status='') {
    return is_autorize_status(ACCESS_CLASSIC, $user_status);
}

/**
 * Returns if user is, at least, an administrator.
 *
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_admin($user_status='') {
    return is_autorize_status(ACCESS_ADMINISTRATOR, $user_status);
}

/**
 * Returns if user is a webmaster.
 *
 * @param string $user_status used if $user not initialized
 * @return bool
 */
function is_webmaster($user_status='') {
    return is_autorize_status(ACCESS_WEBMASTER, $user_status);
}

/**
 * Returns if current user can edit/delete/validate a comment.
 *
 * @param string $action edit/delete/validate
 * @param int $comment_author_id
 * @return bool
 */
function can_manage_comment($action, $comment_author_id) {
    global $user, $conf;

    if (is_a_guest()) {
        return false;
    }

    if (!in_array($action, array('delete','edit', 'validate'))) {
        return false;
    }

    if (is_admin()) {
        return true;
    }

    if ('edit' == $action and $conf['user_can_edit_comment']) {
        if ($comment_author_id == $user['id']) {
            return true;
        }
    }

    if ('delete' == $action and $conf['user_can_delete_comment']) {
        if ($comment_author_id == $user['id']) {
            return true;
        }
    }

    return false;
}

/**
 * Compute sql WHERE condition with restrict and filter data.
 * "FandF" means Forbidden and Filters.
 *
 * @param array $condition_fields one witch fields apply each filter
 *    - forbidden_categories
 *    - visible_categories
 *    - forbidden_images
 *    - visible_images
 * @param string $prefix_condition prefixes query if condition is not empty
 * @param boolean $force_one_condition use at least "1 = 1"
 * @return string
 */
function get_sql_condition_FandF($condition_fields, $prefix_condition=null, $force_one_condition=false) {
    global $user, $filter, $conn;

    $sql_list = array();

    foreach ($condition_fields as $condition => $field_name) {
        switch($condition)
            {
            case 'forbidden_categories': {
                if (!empty($user['forbidden_categories'])) {
                    $sql_list[] = $field_name.' NOT IN ('.$user['forbidden_categories'].')';
                }
                break;
            }
            case 'visible_categories': {
                if (!empty($filter['visible_categories'])) {
                    $sql_list[] = $field_name.' IN ('.$filter['visible_categories'].')';
                }
                break;
            }
            case 'visible_images':
                if (!empty($filter['visible_images'])) {
                    $sql_list[] = $field_name.' IN ('.$filter['visible_images'].')';
                }
                // note there is no break - visible include forbidden
            case 'forbidden_images':
                if (!empty($user['image_access_list']) or $user['image_access_type']!='NOT IN') {
                    $table_prefix=null;
                    if ($field_name=='id') {
                        $table_prefix = '';
                    } elseif ($field_name=='i.id') {
                        $table_prefix = 'i.';
                    }
                    if (isset($table_prefix)) {
                        $sql_list[] = $table_prefix.'level<='.$user['level'];
                    } elseif (!empty($user['image_access_list']) and !empty($user['image_access_type'])) {
                        $sql_list[] = $field_name.' '.$user['image_access_type'].' ('.$user['image_access_list'].')';
                    }
                }
                break;
            default: {
                die('Unknown condition: '.$condition);
                break;
            }
            }
    }

    if (count($sql_list) > 0) {
        $sql = '('.implode(' AND ', $sql_list).')';
    } else {
        $sql = $force_one_condition ? '1 = 1' : '';
    }

    if (isset($prefix_condition) and !empty($sql)) {
        $sql = $prefix_condition.' '.$sql;
    }

    return $sql;
}

/**
 * Returns sql WHERE condition for recent photos/albums for current user.
 *
 * @param string $db_field
 * @return string
 */
function get_recent_photos_sql($db_field) {
    global $user, $conn;

    if (!isset($user['last_photo_date'])) {
        return '0=1';
    }

    return $db_field.'>=LEAST('
        .$conn->db_get_recent_period_expression($user['recent_period'])
        .','.$conn->db_get_recent_period_expression(1,$user['last_photo_date']).')';
}
