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

namespace Phyxo\Model\Repository;

use Phyxo\DBLayer\iDBLayer;
use Phyxo\Conf;
use Phyxo\Functions\Plugin;
use Phyxo\Functions\Utils;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\UserCacheRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\GroupRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserAccessRepository;
use App\Repository\ThemeRepository;

class Users
{
    private $conn, $conf, $user, $cache;

    public function __construct(iDBLayer $conn, Conf $conf, array $user, array $cache)
    {
        $this->conn = $conn;
        $this->conf = $conf;
        $this->user = $user;
        $this->cache = $cache;

        Plugin::add_event_handler('try_log_user', [$this, 'login']);
    }

    /**
     * Checks if an email is well formed and not already in use.
     *
     * @param int $user_id
     * @param string $mail_address
     * @return string|void error message or nothing
     */
    public function validateMailAddress($user_id, $mail_address)
    {
        if (empty($mail_address) && !($this->conf['obligatory_user_mail_address']
            && in_array(\Phyxo\Functions\Utils::script_basename(), ['register', 'profile']))) {
            return;
        }

        if (!\Phyxo\Functions\Utils::email_check_format($mail_address)) {
            return \Phyxo\Functions\Language::l10n('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
        }

        if (!empty($mail_address) && (new UserRepository($this->conn))->isEmailExistsExceptUser($mail_address, $user_id)) {
            return \Phyxo\Functions\Language::l10n('this email address is already in use');
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
    public function registerUser($login, $password, $mail_address, $notify_admin = true, &$errors = [], $notify_user = false)
    {
        if ($login == '') {
            $errors[] = \Phyxo\Functions\Language::l10n('Please, enter a login');
        }
        if (preg_match('/^.* $/', $login)) {
            $errors[] = \Phyxo\Functions\Language::l10n('login mustn\'t end with a space character');
        }
        if (preg_match('/^ .*$/', $login)) {
            $errors[] = \Phyxo\Functions\Language::l10n('login mustn\'t start with a space character');
        }
        if ($this->getUserId($login)) {
            $errors[] = \Phyxo\Functions\Language::l10n('this login is already used');
        }
        if ($login != strip_tags($login)) {
            $errors[] = \Phyxo\Functions\Language::l10n('html tags are not allowed in login');
        }
        $mail_error = $this->validateMailAddress(null, $mail_address);
        if ('' != $mail_error) {
            $errors[] = $mail_error;
        }

        $errors = Plugin::trigger_change(
            'register_user_check',
            $errors,
            [
                'username' => $login,
                'password' => $password,
                'email' => $mail_address,
            ]
        );

        // if no error until here, registration of the user
        if (empty($errors)) {
            $insert = [
                $this->conf['user_fields']['username'] => $this->conn->db_real_escape_string($login),
                $this->conf['user_fields']['password'] => $this->passwordHash($password),
                $this->conf['user_fields']['email'] => $mail_address
            ];

            $user_id = (new UserRepository($this->conn))->addUser($insert);

            // Assign by default groups
            $result = (new GroupRepository($this->conn))->findByField('is_default', true, 'ORDER BY id ASC');
            $inserts = [];
            while ($row = $this->conn->db_fetch_assoc($result)) {
                $inserts[] = [
                    'user_id' => $user_id,
                    'group_id' => $row['id']
                ];
            }

            if (count($inserts) != 0) {
                (new UserGroupRepository($this->conn))->massInserts(['user_id', 'group_id'], $inserts);
            }

            $override = null;
            if ($notify_admin && $this->conf['browser_language']) {
                if (!\Phyxo\Functions\Language::get_browser_language($override['language'])) {
                    $override = null;
                }
            }
            $this->createUserInfos($user_id, $override);

            if ($notify_admin && $this->conf['email_admin_on_new_user']) {
                $admin_url = \Phyxo\Functions\URL::get_absolute_root_url() . 'admin/index.php?page=user_list&username=' . $login;

                $keyargs_content = [
                    \Phyxo\Functions\Language::get_l10n_args('User: %s', stripslashes($login)),
                    \Phyxo\Functions\Language::get_l10n_args('Email: %s', $mail_address),
                    \Phyxo\Functions\Language::get_l10n_args(''),
                    \Phyxo\Functions\Language::get_l10n_args('Admin: %s', $admin_url),
                ];

                \Phyxo\Functions\Mail::mail_notification_admins(
                    \Phyxo\Functions\Language::get_l10n_args('Registration of %s', stripslashes($login)),
                    $keyargs_content
                );
            }

            if ($notify_user && \Phyxo\Functions\Utils::email_check_format($mail_address)) {
                $keyargs_content = [
                    \Phyxo\Functions\Language::get_l10n_args('Hello %s,', stripslashes($login)),
                    \Phyxo\Functions\Language::get_l10n_args('Thank you for registering at %s!', $this->conf['gallery_title']),
                    \Phyxo\Functions\Language::get_l10n_args('', ''),
                    \Phyxo\Functions\Language::get_l10n_args('Here are your connection settings', ''),
                    \Phyxo\Functions\Language::get_l10n_args('Username: %s', stripslashes($login)),
                    \Phyxo\Functions\Language::get_l10n_args('Password: %s', stripslashes($password)),
                    \Phyxo\Functions\Language::get_l10n_args('Email: %s', $mail_address),
                    \Phyxo\Functions\Language::get_l10n_args('', ''),
                    \Phyxo\Functions\Language::get_l10n_args(
                        'If you think you\'ve received this email in error, please contact us at %s',
                        \Phyxo\Functions\Utils::get_webmaster_mail_address()
                    ),
                ];

                \Phyxo\Functions\Mail::mail(
                    $mail_address,
                    [
                        'subject' => '[' . $this->conf['gallery_title'] . '] ' . \Phyxo\Functions\Language::l10n('Registration'),
                        'content' => \Phyxo\Functions\Language::l10n_args($keyargs_content),
                        'content_format' => 'text/plain',
                    ]
                );
            }

            Plugin::trigger_notify(
                'register_user',
                [
                    'id' => $user_id,
                    'username' => $login,
                    'email' => $mail_address,
                ]
            );

            return $user_id;
        } else {
            return false;
        }
    }

    /**
     * Fetches user data from database.
     * Same that getUserData() but with additional tests for guest.
     *
     * @param int $user_id
     * @param boolean $user_cache
     * @return array
     */
    public function buildUser($user_id, $use_cache = true)
    {
        $user['id'] = $user_id;
        $user = array_merge($user, $this->getUserData($user_id, $use_cache));

        if ($user['id'] == $this->conf['guest_id'] and $user['status'] != 'guest') {
            $user['status'] = 'guest';
            $user['internal_status']['guest_must_be_guest'] = true;
        }

        // Check user theme
        if (!isset($user['theme_name'])) {
            $user['theme'] = $this->getDefaultTheme();
        }

        return $user;
    }

    /**
     * Performs all required actions for user login.
     *
     * @param int $user_id
     * @param bool $remember_me
     */
    public function logUser($user_id, $remember_me)
    {
        if ($remember_me && $this->conf['authorize_remembering']) {
            $now = time();
            $key = $this->calculateAutoLoginKey($user_id, $now, $username);
            if ($key !== false) {
                $cookie = $user_id . '-' . $now . '-' . $key;
                setcookie(
                    $this->conf['remember_me_name'],
                    $cookie,
                    time() + $this->conf['remember_me_length'],
                    Utils::cookie_path(),
                    ini_get('session.cookie_domain'),
                    ini_get('session.cookie_secure'),
                    ini_get('session.cookie_httponly')
                );
            }
        } else { // make sure we clean any remember me ...
            setcookie($this->conf['remember_me_name'], '', 0, Utils::cookie_path(), ini_get('session.cookie_domain'));
        }

        $_SESSION['pwg_uid'] = (int)$user_id;
        $this->user['id'] = $_SESSION['pwg_uid'];
        Plugin::trigger_notify('user_login', $this->user['id']);
    }

    /**
     * Performs auto-connection when cookie remember_me exists.
     *
     * @return bool
     */
    public function autoLogin()
    {
        if (isset($_COOKIE[$this->conf['remember_me_name']])) {
            $cookie = explode('-', stripslashes($_COOKIE[$this->conf['remember_me_name']]));
            if (count($cookie) === 3
                and is_numeric(@$cookie[0]) // user id
            and is_numeric(@$cookie[1]) // time
            and time() - $this->conf['remember_me_length'] <= @$cookie[1]
                and time() >= @$cookie[1] /*cookie generated in the past*/) {
                $key = $this->calculateAutoLoginKey($cookie[0], $cookie[1], $username);
                if ($key !== false and $key === $cookie[2]) {
                    $this->logUser($cookie[0], true);
                    Plugin::trigger_notify('login_success', stripslashes($username));
                    return true;
                }
            }
            setcookie($this->conf['remember_me_name'], '', 0, Utils::cookie_path(), ini_get('session.cookie_domain'));
        }

        return false;
    }

    /**
     * Tries to login a user given username and password.
     *
     * @param string $username
     * @param string $password
     * @param bool $remember_me
     * @return bool
     */
    public function tryLogUser($username, $password, $remember_me)
    {
        return Plugin::trigger_change('try_log_user', false, $username, $password, $remember_me);
    }

    /**
     * Default method for user login, can be overwritten with 'try_log_user' trigger.
     * @see tryLogUser()
     *
     * @param string $username
     * @param string $password
     * @param bool $remember_me
     * @return bool
     */
    public function login($success, $username, $password, $remember_me)
    {
        if ($success === true) {
            return true;
        }

        $result = (new UserRepository($this->conn))->findByUsername($username);
        $row = $this->conn->db_fetch_assoc($result);
        if (empty($row)) {
            return false;
        }

        if ($this->passwordVerify($password, $row['password'], $row['id'])) {
            $this->logUser($row['id'], $remember_me);
            Plugin::trigger_notify('login_success', stripslashes($username)); // @TODO: remove stripslashes
            return true;
        }
        Plugin::trigger_notify('login_failure', stripslashes($username));  // @TODO: remove stripslashes

        return false;
    }

    /**
     * Performs all the cleanup on user logout.
     */
    public function logoutUser()
    {
        Plugin::trigger_notify('user_logout', isset($_SESSION['pwg_uid']) ? $_SESSION['pwg_uid'] : null);

        $_SESSION = [];
        session_unset();
        session_destroy();
        setcookie(session_name($this->conf['session_name']), '', 0, ini_get('session.cookie_path'), ini_get('session.cookie_domain'));
        setcookie($this->conf['remember_me_name'], '', 0, Utils::cookie_path(), ini_get('session.cookie_domain'));
    }

    /**
     * Finds informations related to the user identifier.
     *
     * @param int $user_id
     * @param boolean $use_cache
     * @return array
     */
    public function getUserData($user_id, $use_cache = false)
    {
        $result = (new UserRepository($this->conn))->findById($user_id);
        $user = $this->conn->db_fetch_assoc($result);

        // retrieve user info

        $result = (new UserInfosRepository($this->conn))->getCompleteUserInfos($user_id);
        $user_infos = $this->conn->db_fetch_assoc($result);

        // then merge basic + additional user data
        $userdata = array_merge($user, $user_infos);

        foreach ($userdata as &$value) {
            // If the field is true or false, the variable is transformed into a boolean value.
            if (!is_null($value) && $this->conn->is_boolean($value)) {
                $value = $this->conn->get_boolean($value);
            }
        }
        unset($value);
        if ($use_cache) {
            if (!isset($userdata['need_update']) or !is_bool($userdata['need_update']) or $userdata['need_update'] === true) {
                $userdata['cache_update_time'] = time();

                // Set need update are done
                $userdata['need_update'] = false;

                $userdata['forbidden_categories'] = $this->calculatePermissions($userdata['id'], $userdata['status']);

                /* now we build the list of forbidden images (this list does not contain
                 * images that are not in at least an authorized category)
                 */
                $result = (new ImageRepository($this->conn))->getForbiddenImages(explode(', ', $userdata['forbidden_categories']), $userdata['level']);
                $forbidden_ids = $this->conn->result2array($result, null, 'id');

                if (empty($forbidden_ids)) {
                    $forbidden_ids[] = 0;
                }
                $userdata['image_access_type'] = 'NOT IN'; //TODO maybe later
                $userdata['image_access_list'] = implode(',', $forbidden_ids);

                $userdata['nb_total_images'] = (new ImageCategoryRepository($this->conn))->countTotalImages(
                    explode(', ', $userdata['forbidden_categories']),
                    $userdata['image_access_type'],
                    $forbidden_ids
                );

                // now we update user cache categories
                $user_cache_cats = \Phyxo\Functions\Category::get_computed_categories($userdata, null);
                if (!$this->isAdmin($userdata['status'])) { // for non admins we forbid categories with no image (feature 1053)
                    $forbidden_ids = [];
                    foreach ($user_cache_cats as $cat) {
                        if ($cat['count_images'] == 0) {
                            $forbidden_ids[] = $cat['cat_id'];
                            \Phyxo\Functions\Category::remove_computed_category($user_cache_cats, $cat);
                        }
                    }
                    if (!empty($forbidden_ids)) {
                        if (empty($userdata['forbidden_categories'])) {
                            $userdata['forbidden_categories'] = implode(',', $forbidden_ids);
                        } else {
                            $userdata['forbidden_categories'] .= ',' . implode(',', $forbidden_ids);
                        }
                    }
                }

                // delete user cache
                $this->conn->db_write_lock(USER_CACHE_CATEGORIES_TABLE);
                (new UserCacheCategoriesRepository($this->conn))->deleteByUserIds([$userdata['id']]);
                (new UserCacheCategoriesRepository($this->conn))->insertUserCacheCategories(
                    [
                        'user_id', 'cat_id',
                        'date_last', 'max_date_last', 'nb_images', 'count_images', 'nb_categories', 'count_categories'
                    ],
                    $user_cache_cats
                );
                $this->conn->db_unlock();

                // update user cache
                $this->conn->db_start_transaction();
                try {
                    (new UserCacheRepository($this->conn))->deleteUserCache($userdata['id']);
                    (new UserCacheRepository($this->conn))->insertUserCache(
                        [
                            'user_id' => $userdata['id'],
                            'need_update' => $userdata['need_update'],
                            'cache_update_time' => $userdata['cache_update_time'],
                            'forbidden_categories' => $userdata['forbidden_categories'],
                            'nb_total_images' => $userdata['nb_total_images'],
                            'last_photo_date' => !empty($userdata['last_photo_date']) ? $userdata['last_photo_date'] : '',
                            'image_access_type' => $userdata['image_access_type'],
                            'image_access_list' => $userdata['image_access_list']
                        ]
                    );

                    $this->conn->db_commit();
                } catch (\Exception $e) {
                    $this->conn->db_rollback();
                }
            }
        }

        return $userdata;
    }

    /**
     * Creates user informations based on default values.
     *
     * @param int|int[] $user_ids
     * @param array $override_values values used to override default user values
     */
    public function createUserInfos($user_ids, $override_values = null)
    {
        if (!is_array($user_ids)) {
            $user_ids = [$user_ids];
        }

        if (!empty($user_ids)) {
            $inserts = [];
            list($dbnow) = $this->conn->db_fetch_row($this->conn->db_query('SELECT NOW();'));

            $default_user = $this->getDefaultUserInfo(false);
            if ($default_user === false) {
                // Default on structure are used
                $default_user = [];
            }

            if (!is_null($override_values)) {
                $default_user = array_merge($default_user, $override_values);
            }

            foreach ($user_ids as $user_id) {
                $level = isset($default_user['level']) ? $default_user['level'] : 0;
                if ($user_id == $this->conf['webmaster_id']) {
                    $status = 'webmaster';
                    $level = max($this->conf['available_permission_levels']);
                } elseif (($user_id == $this->conf['guest_id']) or ($user_id == $this->conf['default_user_id'])) {
                    $status = 'guest';
                } else {
                    $status = 'normal';
                }

                $insert = array_merge(
                    $default_user,
                    [
                        'user_id' => $user_id,
                        'status' => $status,
                        'registration_date' => $dbnow,
                        'level' => $level
                    ]
                );

                $inserts[] = $insert;
            }

            (new UserInfosRepository($this->conn))->massInserts(array_keys($inserts[0]), $inserts);
        }
    }

    /**
     * Returns user identifier thanks to his name.
     *
     * @param string $username
     * @param int|false
     */
    public function getUserId($username)
    {
        $result = (new UserRepository($this->conn))->findByUsername($username);
        if ($this->conn->db_num_rows($result) === 0) {

            return false;
        } else {
            $user = $this->conn->db_fetch_assoc($result);

            return $user['id'];
        }
    }

    /**
     * Returns user identifier thanks to his email.
     *
     * @param string $email
     * @param int|false
     */
    public function getUserIdByEmail($email)
    {
        $result = (new UserRepository($this->conn))->findByEmail($email);
        if ($this->conn->db_num_rows($result) == 0) {
            return false;
        } else {
            $user = $this->conn->db_fetch_assoc($result);

            return $user['id'];
        }
    }

    /**
     * Returns a array with default user valuees.
     *
     * @param convert_str ceonferts 'true' and 'false' into booleans
     * @return array
     */
    public function getDefaultUserInfo($convert_str = true)
    {
        if (!isset($this->cache['default_user'])) {
            $result = (new UserInfosRepository($this->conn))->findByUserId($this->conf['default_user_id']);
            if ($this->conn->db_num_rows($result) > 0) {
                $this->cache['default_user'] = $this->conn->db_fetch_assoc($result);

                unset($this->cache['default_user']['user_id'], $this->cache['default_user']['status'], $this->cache['default_user']['registration_date']);


            } else {
                $this->cache['default_user'] = false;
            }
        }

        if (is_array($this->cache['default_user']) and $convert_str) {
            $default_user = $this->cache['default_user'];
            foreach ($default_user as &$value) {
                // If the field is true or false, the variable is transformed into a boolean value.
                if (!is_null($value) && $this->conn->is_boolean($value)) {
                    $value = $this->conn->get_boolean($value);
                }
            }
            return $default_user;
        } else {
            return $this->cache['default_user'];
        }
    }

    /**
     * Returns a default user value.
     *
     * @param string $value_name
     * @param mixed $default
     * @return mixed
     */
    public function getDefaultUserValue($value_name, $default)
    {
        $default_user = $this->getDefaultUserInfo(true);
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
    public function getDefaultTheme()
    {
        $theme = $this->getDefaultUserValue('theme', PHPWG_DEFAULT_TEMPLATE);
        if (\Phyxo\Functions\Theme::check_theme_installed($theme)) {
            return $theme;
        }

        // let's find the first available theme
        $result = (new ThemeRepository($this->conn))->findAll();
        $active_themes = array_keys($this->conn->result2array($result, 'id', 'name'));

        return $active_themes[0];
    }

    /**
     * Returns the default language.
     *
     * @return string
     */
    public function getDefaultLanguage()
    {
        return $this->getDefaultUserValue('language', PHPWG_DEFAULT_LANGUAGE);
    }

    /**
     * Returns the auto login key for an user or false if the user is not found.
     *
     * @param int $user_id
     * @param int $time
     * @param string &$username fille with corresponding username
     * @return string|false
     */
    public function calculateAutoLoginKey($user_id, $time, &$username)
    {
        $result = (new UserRepository($this->conn))->findById($user_id);
        if ($this->conn->db_num_rows($result) > 0) {
            $row = $this->conn->db_fetch_assoc($result);
            $data = $time . $user_id . $row['username'];
            $key = base64_encode(hash_hmac('sha1', $data, $this->conf['secret_key'] . $row['password'], true));
            return $key;
        }

        return false;
    }

    /**
     * Hashes a password
     *
     * @param string $password plain text
     * @return string
     */
    public function passwordHash($password)
    {
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
    public function passwordVerify($password, $hash, $user_id = null)
    {
        if (empty($hash) || strpos($hash, '$P') !== false || $hash == md5($password)) {
            $hash = $this->passwordHash($password);

            (new UserRepository($this->conn))->updateUser(['password' => $hash], $user_id);
        }

        return password_verify($password, $hash);
    }

    /**
     *  checks the activation key: does it match the expected pattern? is it
     *  linked to a user? is this user allowed to reset his password?
     *
     * @return mixed (user_id if OK, false otherwise)
     */
    public function checkPasswordResetKey($key)
    {
        if (!preg_match('/^[a-z0-9]{20}$/i', $key)) {
            throw new \Exception(\Phyxo\Functions\Language::l10n('Invalid key'));
        }

        $result = (new UserInfosRepository($this->conn))->findByActivationKey($key);
        if ($this->conn->db_num_rows($result) == 0) {
            throw new \Exception(\Phyxo\Functions\Language::l10n('Invalid key'));
        }

        $userdata = $this->conn->db_fetch_assoc($result);

        if ($this->isGuest($userdata['status']) or $this->isGeneric($userdata['status'])) {
            throw new \Exception(\Phyxo\Functions\Language::l10n('Password reset is not allowed for this user'));
        }

        return $userdata['user_id'];
    }

    /**
     * Return user status.
     *
     * @param string $user_status used if $user not initialized
     * @return string
     */
    protected function getUserStatus($user_status = '')
    {
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
    public function getAccessTypeStatus($user_status = '')
    {
        switch ($this->getUserStatus($user_status)) {
            case 'guest':
                {
                    $access_type_status = ($this->conf['guest_access'] ? ACCESS_GUEST : ACCESS_FREE);
                    break;
                }
            case 'generic':
                {
                    $access_type_status = ACCESS_GUEST;
                    break;
                }
            case 'normal':
                {
                    $access_type_status = ACCESS_CLASSIC;
                    break;
                }
            case 'admin':
                {
                    $access_type_status = ACCESS_ADMINISTRATOR;
                    break;
                }
            case 'webmaster':
                {
                    $access_type_status = ACCESS_WEBMASTER;
                    break;
                }
            default:
                {
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
    public function isAuthorizeStatus($access_type, $user_status = '')
    {
        return ($this->getAccessTypeStatus($user_status) >= $access_type);
    }

    /**
     * Abord script if user has no access to a particular ACCESS_*
     *
     * @return int $access_type one of ACCESS_* constants
     * @param string $user_status used if $user not initialized
     */
    public function checkStatus($access_type, $user_status = '')
    {
        return true; // @TODO: check roles or use voters

        if (!$this->isAuthorizeStatus($access_type, $user_status)) {
            \Phyxo\Functions\HTTP::access_denied();
        }
    }

    /**
     * Returns if user is generic.
     *
     * @param string $user_status used if $user not initialized
     * @return bool
     */
    public function isGeneric($user_status = '')
    {
        return $this->getUserStatus($user_status) == 'generic';
    }

    /**
     * Returns if user is a guest.
     *
     * @param string $user_status used if $user not initialized
     * @return bool
     */
    public function isGuest($user_status = '')
    {
        return $this->getUserStatus($user_status) == 'guest';
    }

    /**
     * Returns if user is, at least, a classic user.
     *
     * @param string $user_status used if $user not initialized
     * @return bool
     */
    public function isClassicUser($user_status = '')
    {
        return $this->isAuthorizeStatus(ACCESS_CLASSIC, $user_status);
    }

    /**
     * Returns if user is, at least, an administrator.
     *
     * @param string $user_status used if $user not initialized
     * @return bool
     */
    public function isAdmin($user_status = '')
    {
        return $this->isAuthorizeStatus(ACCESS_ADMINISTRATOR, $user_status);
    }

    /**
     * Returns if user is a webmaster.
     *
     * @param string $user_status used if $user not initialized
     * @return bool
     */
    public function isWebmaster($user_status = '')
    {
        return $this->isAuthorizeStatus(ACCESS_WEBMASTER, $user_status);
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
    public function calculatePermissions($user_id, $user_status)
    {
        $result = (new CategoryRepository($this->conn))->findByField('status', 'private');
        $private_array = $this->conn->result2array($result, null, 'id');

        // retrieve category ids directly authorized to the user
        $result = (new UserAccessRepository($this->conn))->findByUserId($user_id);
        $authorized_array = $this->conn->result2array($result, null, 'cat_id');

        $result = (new UserGroupRepository($this->conn))->findCategoryAuthorizedToTheGroupTheUserBelongs($user_id);
        $authorized_array = array_merge($authorized_array, $this->conn->result2array($result, null, 'cat_id'));

        // uniquify ids : some private categories might be authorized for the
        // groups and for the user
        $authorized_array = array_unique($authorized_array);

        // only unauthorized private categories are forbidden
        $forbidden_array = array_diff($private_array, $authorized_array);

        // if user is not an admin, locked categories are forbidden
        if (!$this->isAdmin($user_status)) {
            $result = (new CategoryRepository($this->conn))->findByField('visible', false);
            $forbidden_array = array_merge($forbidden_array, $this->conn->result2array($result, null, 'id'));
            $forbidden_array = array_unique($forbidden_array);
        }

        if (empty($forbidden_array)) { // at least, the list contains 0 value. This category does not exists so
            // where clauses such as "WHERE category_id NOT IN(0)" will always be true.
            $forbidden_array[] = 0;
        }

        return implode(',', $forbidden_array);
    }

    /**
     * Returns if current user can edit/delete/validate a comment.
     *
     * @param string $action edit/delete/validate
     * @param int $comment_author_id
     * @return bool
     */
    public function canManageComment($action, $comment_author_id)
    {
        if ($this->isGuest()) {
            return false;
        }

        if (!in_array($action, ['delete', 'edit', 'validate'])) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ('edit' == $action and $this->conf['user_can_edit_comment']) {
            if ($comment_author_id == $this->user['id']) {
                return true;
            }
        }

        if ('delete' == $action and $this->conf['user_can_delete_comment']) {
            if ($comment_author_id == $this->user['id']) {
                return true;
            }
        }

        return false;
    }
}
