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

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\Scope\HookScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

class DbContext implements Context
{
    private static $prefix = 'phyxo_';
    private $response_params = array();
    private $last_id;
    private static $conf_loaded = false, $pdo = null;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /** @BeforeScenario */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->parameters = self::getParametersFromScope($scope);
    }

    /**
     * @Given /^a user:$/
     * @Given /^some users:$/
     */
    public function aUser(TableNode $table)
    {
        foreach ($table->getHash() as $user) {
            $this->last_id = $this->addUser($user);
        }
    }

    /**
     * @Given /^a group:$/
     */
    public function aGroup(TableNode $table)
    {
        foreach ($table->getHash() as $group) {
            $this->last_id = $this->addGroup($group);
        }
    }

    /**
     * @Given /^an image:$/
     * @Given /^images:$/
     */
    public function images(TableNode $table)
    {
        foreach ($table->getHash() as $image) {
            $this->last_id = $this->addImage($image);
            if (!empty($image['tags'])) {
                $this->addTags($image['tags'], $this->last_id);
            }
        }
    }

    /**
     * @Given /^an album:$/
     * @Given /^albums:$/
     */
    public function albums(TableNode $table)
    {
        foreach ($table->getHash() as $album) {
            $this->last_id = $this->addAlbum($album);
        }
    }

    /**
     * @Given /^tags:$/
     */
    public function tags(TableNode $table)
    {
        foreach ($table->getHash() as $tag) {
            $this->last_id = $this->addTag($tag['name']);
        }
    }

    /**
     * @Given /^add tag "([^"]*)" on "([^"]*)" (not)? validated$/
     */
    public function addTagOnImageValidatedOrNot($tag, $image_id, $validated = true)
    {
        if ($validated == 'not') {
            $validated = false;
        }
        if (preg_match('`^SAVED:(.*)$`', $image_id, $matches)) {
            $image_id = $this->getSaved($matches[1]);
        }

        $this->addTags($tag, $image_id, $validated);
    }

    /**
     * @Given /^remove tag "([^"]*)" on "([^"]*)" (not)? validated$/
     */
    public function removeTagOnImageValidatedOrNot($tag, $image_id, $validated = true)
    {
        if ($validated == 'not') {
            $validated = false;
        }
        if (preg_match('`^SAVED:(.*)$`', $image_id, $matches)) {
            $image_id = $this->getSaved($matches[1]);
        }
        $this->dissociateTags($tag, $image_id, $validated);
    }

    /**
     * @Then /^save "([^"]*)"$/
     */
    public function save($id, $value = '')
    {
        $this->response_params[$id] = $this->last_id;
    }

    /**
     * @Then /^save "([^"]*)" from property "([^"]*)"$/
     */
    public function saveFromProperty($id, $property)
    {
        $this->response_params[$id] = $this->theResponseHasProperty($property);
    }

    public function getSaved($id)
    {
        if (isset($this->response_params[$id])) {
            return $this->response_params[$id];
        } else {
            return null;
        }
    }

    /**
     * @Given /^a comment "([^"]*)" on "([^"]*)" by "([^"]*)"$/
     */
    public function aCommentOnBy($comment, $image_name, $username)
    {
        $this->addComment($comment, $image_name, $username);
    }

    /**
     * @Given /^users "([^"]*)" belong to group "([^"]*)"$/
     */
    public function usersBelongToGroup($users, $group)
    {
        $user_ids = explode(',', $users);
        $group_id = preg_replace_callback(
            '`SAVED:(.*)`',
            function ($matches) {
                return $this->getSaved($matches[1]);
            },
            $group
        );
        foreach ($user_ids as $user) {
            $user_id = preg_replace_callback(
                '`SAVED:(.*)`',
                function ($matches) {
                    return $this->getSaved($matches[1]);
                },
                $user
            );
            $this->addUserToGroup($user_id, $group_id);
        }
    }

    /**
     * @Given /^user "([^"]*)" can access "([^"]*)"$/
     */
    public function userCanAccess($username, $album_name)
    {
        $this->userAccess($username, $album_name);
    }

    /**
     * @Given /^user "([^"]*)" cannot access "([^"]*)"$/
     */
    public function userCannotAccess($username, $album_name)
    {
        $this->userAccess($username, $album_name, $remove = true);
    }

    /**
     * @Given /^group "([^"]*)" can access "([^"]*)"$/
     */
    public function groupCanAccess($groupname, $album_name)
    {
        $this->groupAccess($groupname, $album_name);
    }

    /**
     * @When config for :param equals to :value of type :type
     */
    public function configForEqualsToOfType($param, $value, $type)
    {
        if ($type === 'boolean') {
            $this->configForEqualsTo($param, ($value === 'true'));
        } else {
            $this->configForEqualsTo($param, $value);
        }
    }

    /**
     * @Given /^config for "([^"]*)" equals to "([^"]*)"$/
     */
    public function configForEqualsTo($param, $value)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'config WHERE param = :param');
        $stmt->bindValue(':param', $param);
        $stmt->execute();
        $conf = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conf) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'config (param, value) VALUES(:param, :value)');
            $stmt->bindValue(':param', $param);
            $stmt->bindValue(':value', $value);
            $stmt->execute();
        } else {
            $stmt = self::$pdo->prepare('UPDATE ' . self::$prefix . 'config SET value = :value WHERE param = :param');
            $stmt->bindValue(':param', $param);
            $stmt->bindValue(':value', $value);
            $stmt->execute();
        }
    }

    public function getAlbum($album_name)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE name = :name');
        $stmt->bindValue(':name', $album_name);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$album) {
            throw new Exception('Album with name ' . $album_name . ' does not exist' . "\n");
        }

        return $album;
    }

    /**
     * @Given /^associate image "([^"]*)" to "([^"]*)"$/
     */
    public function associateImageToAlbum($image_id, $album_id)
    {
        if (empty($album_id) || empty($image_id)) {
            throw new Exception('Album Id and image Id are mandatory' . "\n");
        }

        if (preg_match('`^SAVED:(.*)$`', $album_id, $matches)) {
            $album_id = $this->getSaved($matches[1]);
        }
        if (preg_match('`^SAVED:(.*)$`', $image_id, $matches)) {
            $image_id = $this->getSaved($matches[1]);
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE id = :id');
        $stmt->bindValue(':id', $album_id, PDO::PARAM_INT);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            throw new Exception('Cannot find an album with id "' . $album_id . '"');
        }
        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'images WHERE id = :id');
        $stmt->bindValue(':id', $image_id, PDO::PARAM_INT);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$image) {
            throw new Exception('Cannot find an image with id "' . $image_id . '"');
        }
        $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'image_category (category_id, image_id) VALUES(:category_id, :image_id)');
        $stmt->bindValue(':category_id', $album_id, PDO::PARAM_INT);
        $stmt->bindValue(':image_id', $image_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function get_token($session_id)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'config WHERE param = :param');
        $stmt->bindValue(':param', 'secret_key');
        $stmt->execute();
        $conf = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conf) {
            return hash_hmac('md5', $session_id, $conf['value']);
        }
    }

    private static function getParametersFromScope(HookScope $scope)
    {
        return $scope->getEnvironment()->getSuite()->getSettings()['contexts'][6]['DbContext']['parameters'];
    }

    /**
     * @BeforeSuite
     */
    public static function prepareDB(BeforeSuiteScope $scope)
    {
        $parameters = self::getParametersFromScope($scope);

        if (!empty($parameters['sql_init_file']) && !empty($parameters['config_file'])
            && is_readable($parameters['sql_init_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters);
            }
            self::executeSqlFile($parameters['sql_init_file']);
        }

        // in case suite has been stopped before end
        if (!empty($parameters['sql_cleanup_file']) && !empty($parameters['config_file'])
            && is_readable($parameters['sql_cleanup_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters);
            }
            self::executeSqlFile($parameters['sql_cleanup_file']);
        }
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(AfterScenarioScope $scope)
    {
        $parameters = self::getParametersFromScope($scope);

        if (!empty($parameters['sql_cleanup_file']) && !empty($parameters['config_file'])
            && is_readable($parameters['sql_cleanup_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters);
            }

            self::executeSqlFile($parameters['sql_cleanup_file']);
        }
    }

    private static function executeSqlFile($filepath)
    {
        $sql_lines = file($filepath);
        $query = '';
        foreach ($sql_lines as $sql_line) {
            $sql_line = trim($sql_line);
            if (preg_match('/(^--|^$)/', $sql_line)) {
                continue;
            }
            $query .= ' ' . $sql_line;
            if (preg_match('/;$/', $sql_line)) {
                $query = trim($query);
                if (!preg_match('/^DROP TABLE/i', $query)) {
                    self::$pdo->query($query);
                }
                $query = '';
            }
        }
    }

    private static function configDb($parameters)
    {
        include($parameters['config_file']);
        self::$prefix = $prefixeTable;

        if ($conf['dblayer'] == 'sqlite') {
            self::$pdo = new \PDO(sprintf('sqlite:%s/db/%s.db', __DIR__ . '/../..', $conf['db_base']));
        } else {
            self::$pdo = new \PDO(sprintf('%s:host=%s;dbname=%s', $conf['dblayer'], $conf['db_host'], $conf['db_base']), $conf['db_user'], $conf['db_password']);
        }
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);


        self::$conf_loaded = true;
    }

    private function addUser(array $params)
    {
        if (empty($params['username']) || empty($params['password'])) {
            throw new Exception('Username and Password for user are mandatory' . "\n");
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'users WHERE username = :username');
        $stmt->bindValue(':username', $params['username']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'users (username, password) VALUES(:username, :password)');
            $stmt->bindValue(':username', $params['username']);
            $stmt->bindValue(':password', password_hash($params['password'], PASSWORD_BCRYPT));
            $stmt->execute();
            $user_id = self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'users'), 'id'));

            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'user_infos (user_id, status, registration_date, level) VALUES(:user_id, :status, :registration_date, :level)');
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            if (empty($params['status'])) {
                $stmt->bindValue(':status', 'normal');
                $params['status'] = 'normal';
            } else {
                $stmt->bindValue(':status', $params['status']);
            }
            $now = new DateTime('now');
            $stmt->bindValue(':registration_date', $now->format('Y-m-d H:i:s'));
            if ($params['status'] == 'webmaster') {
                // to be retrieve from config_default
                // @see src/Phyxo/Model/Repository/Users.php:createUserInfos
                $stmt->bindValue(':level', 8, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':level', 0, PDO::PARAM_INT);
            }
            $stmt->execute();
        } else {
            $user_id = $user['id'];
        }

        return $user_id;
    }

    private function addGroup(array $params)
    {
        if (empty($params['name'])) {
            throw new Exception('Name for group is mandatory' . "\n");
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }
        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'groups WHERE name = :name');
        $stmt->bindValue(':name', $params['name']);
        $stmt->execute();
        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$group) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'groups (name) VALUES(:name)');
            $stmt->bindValue(':name', $params['name']);
            $stmt->execute();
            $group_id = self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'groups'), 'id'));
        } else {
            $group_id = $group['id'];
        }

        return $group_id;
    }

    private function addImage(array $params)
    {
        if (empty($params['album']) || empty($params['name'])) {
            throw new Exception('Album name and image name are mandatory' . "\n");
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE name = :name');
        $stmt->bindValue(':name', $params['album']);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            throw new Exception('Album with name ' . $params['album'] . ' does not exist' . "\n");
        }

        if (empty($params['file'])) {
            $params['file'] = __DIR__ . '/../media/blank.png';
        }
        $md5sum = md5($params['file']);

        $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'images (name, file, path, date_creation, date_available, author, md5sum) VALUES(:name, :file, :path, :date_creation, :date_available, :author, :md5sum)');
        $stmt->bindValue(':name', $params['name']);
        $stmt->bindValue(':file', basename($params['file']));

        $now = new DateTime('now');
        $upload_dir = $this->parameters['upload_dir'] . $now->format('Y/m/d');
        $path = $upload_dir . sprintf('/%s-%s.png', $now->format('YmdHis'), substr($md5sum, 0, 8));

        $stmt->bindValue(':path', $path);
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        copy($params['file'], $path);
        if (empty($params['date_creation'])) {
            $stmt->bindValue(':date_creation', $now->format('Y-m-d H:i:s'));
        } else {
            $stmt->bindValue(':date_creation', $params['date_creation']);
        }
        if (!empty($params['date_available'])) {
            $stmt->bindValue(':date_available', $params['date_available']);
        } else {
            $stmt->bindValue(':date_available', $now->format('Y-m-d H:i:s'));
        }
        if (!empty($params['author'])) {
            $stmt->bindValue(':author', $params['author']);
        } else {
            $stmt->bindValue(':author', '');
        }
        $stmt->bindValue(':md5sum', $md5sum);
        $stmt->execute();
        $image_id = self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'images'), 'id'));

        $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'image_category (image_id, category_id) VALUES(:image_id, :category_id)');
        $stmt->bindValue(':image_id', $image_id, PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $album['id'], PDO::PARAM_INT);
        $stmt->execute();

        return $image_id;
    }

    private function addAlbum(array $params)
    {
        if (empty($params['name'])) {
            throw new Exception('Album name is mandatory' . "\n");
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE name = :name');
        $stmt->bindValue(':name', $params['name']);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'categories (name, status, commentable, global_rank) VALUES(:name, :status, :commentable, 1)');
            $stmt->bindValue(':name', $params['name']);
            if (empty($params['status'])) {
                $params['status'] = 'private';
            }
            $stmt->bindValue(':status', $params['status']);

            if (!empty($params['commentable'])) {
                $commentable = (boolean)$params['commentable'];
            } else {
                $commentable = true;
            }
            $stmt->bindValue(':commentable', $commentable, PDO::PARAM_BOOL);
            $stmt->execute();
            $album_id = self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'categories'), 'id'));

            $stmt = self::$pdo->prepare('UPDATE ' . self::$prefix . 'categories SET uppercats = :uppercats WHERE id = :id');
            $stmt->bindValue(':uppercats', $album_id, PDO::PARAM_INT);
            $stmt->bindValue(':id', $album_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $album_id = $album['id'];
        }

        return $album_id;
    }

    private function addUserToGroup($user_id, $group_id)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new Exception('User with id "' . $user_id . '" does not exist' . "\n");
        }
        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'groups WHERE id = :id');
        $stmt->bindValue(':id', $group_id, PDO::PARAM_INT);
        $stmt->execute();
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new Exception('Group with id "' . $group_id . '" does not exist' . "\n");
        }
        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'user_group WHERE user_id = :user_id AND group_id = :group_id');
        $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(':group_id', $group['id'], PDO::PARAM_INT);
        $stmt->execute();
        $user_group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user_group) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'user_group (user_id, group_id) VALUES(:user_id, :group_id)');
            $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(':group_id', $group['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    private function userAccess($username, $album_name, $remove = false)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE name = :name');
        $stmt->bindValue(':name', $album_name);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            throw new Exception('Album with name ' . $album_name . ' does not exist' . "\n");
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'users WHERE username = :username');
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new Exception('User with username ' . $username . ' does not exist' . "\n");
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'user_access WHERE user_id = :user_id AND cat_id = :cat_id');
        $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(':cat_id', $album['id'], PDO::PARAM_INT);
        $stmt->execute();
        $access = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($remove) {
            if ($access) {
                $stmt = self::$pdo->prepare('DELETE FROM ' . self::$prefix . 'user_access WHERE user_id = :user_id AND cat_id = :cat_id');
                $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
                $stmt->bindValue(':cat_id', $album['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
        } else {
            if (!$access) {
                $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'user_access (user_id, cat_id) VALUES(:user_id, :cat_id)');
                $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
                $stmt->bindValue(':cat_id', $album['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        $stmt = self::$pdo->prepare('DELETE FROM ' . self::$prefix . 'user_cache WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
    }

    private function groupAccess($groupname, $album_name)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'categories WHERE name = :name');
        $stmt->bindValue(':name', $album_name);
        $stmt->execute();
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            throw new Exception('Album with name ' . $album_name . ' does not exist' . "\n");
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'groups WHERE name = :name');
        $stmt->bindValue(':name', $groupname);
        $stmt->execute();
        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$group) {
            throw new Exception('Group with name ' . $groupname . ' does not exist' . "\n");
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'group_access WHERE group_id = :group_id AND cat_id = :cat_id');
        $stmt->bindValue(':group_id', $group['id'], PDO::PARAM_INT);
        $stmt->bindValue(':cat_id', $album['id'], PDO::PARAM_INT);
        $stmt->execute();
        $access = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$access) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'group_access (group_id, cat_id) VALUES(:group_id, :cat_id)');
            $stmt->bindValue(':group_id', $group['id'], PDO::PARAM_INT);
            $stmt->bindValue(':cat_id', $album['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    private function addComment($content, $photo_name, $username)
    {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * from ' . self::$prefix . 'images WHERE name = :name');
        $stmt->bindValue(':name', $photo_name);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            throw new Exception('Image with name ' . $photo_name . ' does not exist' . "\n");
        }

        $stmt = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'users WHERE username = :username');
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User with username ' . $username . ' does not exist' . "\n");
        }
        $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'comments (image_id, author, author_id, content, date, validation_date, validated) VALUES(:image_id, :author, :author_id, :content, :date, :validation_date, :validated)');
        $stmt->bindValue(':image_id', $image['id'], PDO::PARAM_INT);
        $stmt->bindValue(':author', $username);
        $stmt->bindValue(':author_id', $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(':content', $content);
        $yesterday = (new DateTime('now - 1 day'))->format('Y-m-d H:i:s');
        $stmt->bindValue(':date', $yesterday);
        $stmt->bindValue(':validation_date', $yesterday);
        $stmt->bindValue(':validated', true, PDO::PARAM_BOOL);
        $stmt->execute();

        return self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'comments'), 'id'));
    }

    private function addTag($tag_name)
    {
        if (!defined('PHPWG_ROOT_PATH')) {
            define('PHPWG_ROOT_PATH', __DIR__ . '/../../');
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt = self::$pdo->prepare('SELECT * from ' . self::$prefix . 'tags WHERE name = :name');
        $stmt->bindValue(':name', $tag_name);
        $stmt->execute();
        $tag = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tag) {
            $stmt = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'tags (name, url_name) VALUES(:name, :url_name)');
            $stmt->bindValue(':name', $tag_name);
            $stmt->bindValue(':url_name', \Phyxo\Functions\Language::str2url($tag_name));
            $stmt->execute();

            return self::$pdo->lastInsertId(sprintf('%s_%s_seq', strtolower(self::$prefix . 'tags'), 'id'));
        } else {
            return $tag['id'];
        }
    }

    private function dissociateTags($param_tags, $image_id, $validated = true)
    {
        if (preg_match('`\[(.*)]`', $param_tags, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
        } else {
            $tags = array($param_tags);
        }
        foreach ($tags as &$tag) {
            if (preg_match('`^SAVED:(.*)$`', $tag, $matches)) {
                $tag = $this->getSaved($matches[1]);
            }
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt_find = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'image_tag WHERE tag_id = :tag_id AND image_id = :image_id');
        $stmt_update = self::$pdo->prepare('UPDATE ' . self::$prefix . 'image_tag SET status = :status, validated = :validated WHERE tag_id = :tag_id AND image_id = :image_id');
        $stmt_delete = self::$pdo->prepare('DELETE FROM ' . self::$prefix . 'image_tag WHERE tag_id = :tag_id AND image_id = :image_id');
        foreach ($tags as $tag_name) {
            $tag_id = $this->addTag($tag_name);
            $stmt_find->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
            $stmt_find->bindValue(':image_id', $image_id, PDO::PARAM_INT);
            $stmt_find->execute();
            $image_tag = $stmt_find->fetch(PDO::FETCH_ASSOC);
            if ($image_tag) {
                if (!$validated) {
                    $stmt_update->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
                    $stmt_update->bindValue(':image_id', $image_id);
                    $stmt_update->bindValue(':status', 0, PDO::PARAM_INT);
                    $stmt_update->bindValue(':validated', $validated, PDO::PARAM_BOOL);
                    $stmt_update->execute();
                } else {
                    $stmt_delete->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
                    $stmt_delete->bindValue(':image_id', $image_id, PDO::PARAM_INT);
                    $stmt_delete->execute();
                }
            }
        }
    }

    private function addTags($param_tags, $image_id, $validated = true)
    {
        if (preg_match('`\[(.*)]`', $param_tags, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
        } else {
            $tags = array($param_tags);
        }
        foreach ($tags as &$tag) {
            if (preg_match('`^SAVED:(.*)$`', $tag, $matches)) {
                $tag = $this->getSaved($matches[1]);
            }
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $stmt_find = self::$pdo->prepare('SELECT * FROM ' . self::$prefix . 'image_tag WHERE tag_id = :tag_id AND image_id = :image_id');
        $stmt_insert = self::$pdo->prepare('INSERT INTO ' . self::$prefix . 'image_tag (tag_id, image_id, validated, status) VALUES(:tag_id, :image_id, :validated, :status)');
        foreach ($tags as $tag_name) {
            $tag_id = $this->addTag($tag_name);
            $stmt_find->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
            $stmt_find->bindValue(':image_id', $image_id, PDO::PARAM_INT);
            $stmt_find->execute();
            $image_tag = $stmt_find->fetch(PDO::FETCH_ASSOC);
            if (!$image_tag) {
                $stmt_insert->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
                $stmt_insert->bindValue(':image_id', $image_id, PDO::PARAM_INT);
                $stmt_insert->bindValue(':status', 1, PDO::PARAM_INT);
                $stmt_insert->bindValue(':validated', $validated, PDO::PARAM_BOOL);
                $stmt_insert->execute();
            }
        }
    }
}
