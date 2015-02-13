<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Behat\Event\SuiteEvent;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Gherkin\Node\TableNode;

class DbContext extends RawMinkContext
{
    private static $conf_loaded = false;
    public static $prefix = 'phyxo_';
    private $response_params = array();
    private $last_id;

    public function __construct(array $parameters) {
        $this->parameters = $parameters;
    }

    /**
     * @Given /^a user:$/
     * @Given /^some users:$/
     */
    public function aUser(TableNode $table) {
        foreach ($table->getHash() as $user) {
            $this->last_id = $this->addUser($user);
        }
    }

    /**
     * @Given /^a group:$/
     */
    public function aGroup(TableNode $table) {
        foreach ($table->getHash() as $group) {
            $this->last_id = $this->addGroup($group);
        }
    }

    /**
     * @Given /^an image:$/
     * @Given /^images:$/
     */
    public function images(TableNode $table) {
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
    public function albums(TableNode $table) {
        foreach ($table->getHash() as $album) {
            $this->last_id = $this->addAlbum($album);
        }
    }

    /**
     * @Given /^tags:$/
     */
    public function tags(TableNode $table) {
        foreach ($table->getHash() as $tag) {
            $this->last_id = $this->addTag($tag['name']);
        }
    }

    /**
     * @Given /^add tag "([^"]*)" on "([^"]*)" (not)? validated$/
     */
    public function addTagOnImageValidatedOrNot($tag, $image_id, $validated=true) {
        if ($validated=='not') {
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
    public function removeTagOnImageValidatedOrNot($tag, $image_id, $validated=true) {
        if ($validated=='not') {
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
    public function save($id, $value='') {
        $this->response_params[$id] = $this->last_id;
    }

    /**
     * @Then /^save "([^"]*)" from property "([^"]*)"$/
     */
    public function saveFromProperty($id, $property) {
        $this->response_params[$id] = $this->getMainContext()->getSubcontext('api')->theResponseHasProperty($property);
    }

    public function getSaved($id) {
        if (isset($this->response_params[$id])) {
            return $this->response_params[$id];
        } else {
            return null;
        }
    }

    /**
     * @Given /^a comment "([^"]*)" on "([^"]*)" by "([^"]*)"$/
     */
    public function aCommentOnBy($comment, $image_name, $username) {
        $this->addComment($comment, $image_name, $username);
    }

    /**
     * @Given /^users "([^"]*)" belong to group "([^"]*)"$/
     */
    public function usersBelongToGroup($users, $group) {
        $user_ids = explode(',', $users);
        $group_id = preg_replace_callback(
            '`SAVED:(.*)`',
            function($matches) {
                return $this->getSaved($matches[1]);
            },
            $group
        );
        foreach ($user_ids as $user) {
            $user_id = preg_replace_callback(
                '`SAVED:(.*)`',
                function($matches) {
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
    public function userCanAccess($username, $album_name) {
        $this->userAccess($username, $album_name);
    }

    /**
     * @Given /^user "([^"]*)" cannot access "([^"]*)"$/
     */
    public function userCannotAccess($username, $album_name) {
        $this->userAccess($username, $album_name, $remove=true);
    }

    /**
     * @Given /^group "([^"]*)" can access "([^"]*)"$/
     */
    public function groupCanAccess($groupname, $album_name) {
        $this->groupAccess($groupname, $album_name);
    }

    /**
     * @Given /^config for "([^"]*)" equals to "([^"]*)"$/
     */
    public function configForEqualsTo($param, $value) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $conf = ORM::for_table(self::$prefix.'config')->where('param', $param)->find_one();

        if (!$conf) {
            $conf = ORM::for_table(self::$prefix.'config')->create();
            $conf->param = $param;
            $conf->value = $value;
            $conf->save();
        } else {
            $conf->value = $value;
            $conf->save();
        }
    }

    public function getAlbum($album_name) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $album = ORM::for_table(self::$prefix.'categories')->where('name', $album_name)->find_one();
        if (!$album) {
            throw new Exception('Album with name '.$album_name.' does not exist'."\n");
        }

        return $album;
    }

    /**
     * @Given /^associate image "([^"]*)" to "([^"]*)"$/
     */
    public function associateImageToAlbum($image_id, $album_id) {
        if (empty($album_id) || empty($image_id)) {
            throw new Exception('Album Id and image Id are mandatory'."\n");
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

        $album = ORM::for_table(self::$prefix.'categories')->where('id', $album_id)->find_one();
        if (!$album) {
            throw new Exception('Cannot find an album with id "'.$album_id.'"');
        }
        $image = ORM::for_table(self::$prefix.'images')->where('id', $image_id)->find_one();
        if (!$image) {
            throw new Exception('Cannot find an image with id "'.$image_id.'"');
        }
        $image_album = ORM::for_table(self::$prefix.'image_category')->create();
        $image_album->category_id = $album_id;
        $image_album->image_id = $image_id;
        $image_album->save();
    }

    public function get_pwg_token($session_id) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $conf = ORM::for_table(self::$prefix.'config')->where('param', 'secret_key')->find_one();
        if ($conf) {
            return hash_hmac('md5', $session_id, $conf->value);
        }
    }

    /**
     * @BeforeSuite
     */
    public static function prepareDB(SuiteEvent $event) {
        $parameters = $event->getContextParameters();
        if (!empty($parameters['sql_init_file']) && !empty($parameters['config_file'])
        && is_readable($parameters['sql_init_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters);
            }

            $sql_content = trim(file_get_contents($parameters['sql_init_file']));
            if (!empty($sql_content)) {
                ORM::get_db()->exec($sql_content);
            }
        }

        // in case suite has been stopped before end
        if (!empty($parameters['sql_cleanup_file']) && !empty($parameters['config_file'])
        && is_readable($parameters['sql_cleanup_file']) && is_readable($parameters['config_file'])) {
            $sql_content = trim(file_get_contents($parameters['sql_cleanup_file']));
            if (!empty($sql_content)) {
                ORM::get_db()->exec($sql_content);
            }
        }
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(ScenarioEvent $event) {
        $parameters = $event->getContext()->parameters;

        if (!empty($parameters['sql_cleanup_file']) && !empty($parameters['config_file'])
        && is_readable($parameters['sql_cleanup_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters);
            }

            $sql_content = trim(file_get_contents($parameters['sql_cleanup_file']));
            if (!empty($sql_content)) {
                ORM::get_db()->exec($sql_content);
            }
        }
    }

    private static function configDb($parameters) {
        include($parameters['config_file']);
        self::$prefix = $prefixeTable;

        if ($conf['dblayer']=='sqlite') {
            // @See src/Phyxo/DBLayer/sqliteConnection.php
            ORM::configure(sprintf('sqlite:%s/db/%s.db', __DIR__.'/../..', $conf['db_base']));
        } else {
            ORM::configure($conf['dblayer'].':host='.$conf['db_host'].';dbname='.$conf['db_base']);
            ORM::configure('username', $conf['db_user']);
            ORM::configure('password', $conf['db_password']);
        }

        ORM::configure('logging', true);

        // primary keys
        ORM::configure(
            'id_column_overrides',
            array(
                self::$prefix.'user_infos' => 'user_id',
                self::$prefix.'image_category' => array('image_id', 'category_id'),
                self::$prefix.'user_access' => array('user_id', 'cat_id'),
                self::$prefix.'group_access' => array('group_id', 'cat_id'),
                self::$prefix.'user_group' => array('user_id', 'group_id'),
                self::$prefix.'image_tag' => array('image_id', 'tag_id'),
                self::$prefix.'config' => 'param',
            )
        );
        self::$conf_loaded = true;
    }

    /* ORM methods */
    private function addUser(array $params) {
        if (empty($params['username']) || empty($params['password'])) {
            throw new Exception('Username and Password for user are mandatory'."\n");
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $user = ORM::for_table(self::$prefix.'users')->where('username', $params['username'])->find_one();
        if (!$user) {
            $user = ORM::for_table(self::$prefix.'users')->create();
            $user->username = $params['username'];
            $user->password = password_hash($params['password'], PASSWORD_BCRYPT);
            $user->save();

            $user_info = ORM::for_table(self::$prefix.'user_infos')->create();
            $user_info->user_id = $user->id;
            if (empty($params['status'])) {
                $user_info->status = 'normal';
                $params['status'] = 'normal';
            } else {
                $user_info->status = $params['status'];
            }
            $now = new DateTime('now');
            $user_info->registration_date = $now->format('Y-m-d H:i:s');
            if ($params['status']=='webmaster') {
                // to be retrieve from config_default
                // @see src/Phyxo/Model/Repository/Users.php:createUserInfos
                $user_info->level = 8;
            } else {
                $user_info->level = 0;
            }
            $user_info->save();
        }

        return $user->id;
    }

    private function addGroup(array $params) {
        if (empty($params['name'])) {
            throw new Exception('Name for group is mandatory'."\n");
        }
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }
        $group = ORM::for_table(self::$prefix.'groups')->where('name', $params['name'])->find_one();
        if (!$group) {
            $group = ORM::for_table(self::$prefix.'groups')->create();
            $group->name = $params['name'];
            $group->save();
        }
        return $group->id;
    }

    private function addImage(array $params) {
        if (empty($params['album']) || empty($params['name'])) {
            throw new Exception('Album name and image name are mandatory'."\n");
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $album = ORM::for_table(self::$prefix.'categories')->where('name', $params['album'])->find_one();
        if (!$album) {
            throw new Exception('Album with name '.$params['album'].' does not exist'."\n");
        }

        if (empty($params['file'])) {
            $params['file'] = __DIR__ .'/../media/blank.png';
        }
        $md5sum = md5($params['file']);

        $image = ORM::for_table(self::$prefix.'images')->create();
        $image->name = $params['name'];
        $image->file = basename($params['file']);

        $now = new DateTime('now');
        $upload_dir = $this->parameters['upload_dir'].$now->format('Y/m/d');
        $path = $upload_dir . sprintf('/%s-%s.png', $now->format('YmdHis'), substr($md5sum, 0, 8));

        $image->path = $path;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        copy($params['file'], $path);
        if (empty($params['date_creation'])) {
            $image->date_creation = $now->format('Y-m-d H:i:s');
        } else {
            $image->date_creation = $params['date_creation'];
        }
        if (!empty($params['date_available'])) {
            $image->date_available = $params['date_available'];
        } else {
            $image->date_available = $now->format('Y-m-d H:i:s');
        }
        if (!empty($params['author'])) {
            $image->author = $params['author'];
        }
        $image->md5sum = $md5sum;
        $image->save();

        $image_category = ORM::for_table(self::$prefix.'image_category')->create();
        $image_category->image_id = $image->id;
        $image_category->category_id = $album->id;
        $image_category->save();

        return $image->id;
    }

    private function addAlbum(array $params) {
        if (empty($params['name'])) {
            throw new Exception('Album name is mandatory'."\n");
        }

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $album = ORM::for_table(self::$prefix.'categories')->where('name', $params['name'])->find_one();
        if (!$album) {
            $album = ORM::for_table(self::$prefix.'categories')->create();
            $album->name = $params['name'];
            if (!empty($params['status'])) {
                $album->status = $params['status'];
            } else {
                $album->status = 'private';
            }
            if (!empty($params['commentable'])) {
                $album->commentable = (boolean) $params['commentable'];
            } else {
                $album->commentable = true;
            }
            $album->global_rank = 1;
            $album->save();

            $album->uppercats = $album->id;
            $album->save();
        }

        return $album->id;
    }

    private function addUserToGroup($user_id, $group_id) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }
        $user = ORM::for_table(self::$prefix.'users')->where('id', $user_id);
        if (!$user) {
            throw new Exception('User with id "'.$user_id.'" does not exist'."\n");
        }
        $group = ORM::for_table(self::$prefix.'groups')->where('id', $group_id);
        if (!$group) {
            throw new Exception('Group with id "'.$group_id.'" does not exist'."\n");
        }
        $user_group = ORM::for_table(self::$prefix.'user_group')
            ->where('user_id', $user->id)
            ->where('group_id', $group->id)
            ->find_one();
        if (!$user_group) {
            $user_group = ORM::for_table(self::$prefix.'user_group')->create();
            $user_group->user_id = $user_id;
            $user_group->group_id = $group_id;
            $user_group->save();
        }
    }

    private function userAccess($username, $album_name, $remove=false) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $album = ORM::for_table(self::$prefix.'categories')->where('name', $album_name)->find_one();
        if (!$album) {
            throw new Exception('Album with name '.$album_name.' does not exist'."\n");
        }

        $user = ORM::for_table(self::$prefix.'users')->where('username', $username)->find_one();
        if (!$user) {
            throw new Exception('User with username '.$username.' does not exist'."\n");
        }

        $access = ORM::for_table(self::$prefix.'user_access')
            ->where('user_id', $user->id)
            ->where('cat_id', $album->id)
            ->find_one();
        if ($remove) {
            if ($access) {
                ORM::for_table(self::$prefix.'user_access')
                    ->where('user_id', $user->id)
                    ->where('cat_id', $album->id)
                    ->delete_many();
            }
        } else {
            if (!$access) {
                $access = ORM::for_table(self::$prefix.'user_access')->create();
                $access->user_id = $user->id;
                $access->cat_id = $album->id;
                $access->save();
            }
        }

        $user_cache = ORM::for_table(self::$prefix.'user_cache')
            ->where('user_id', $user->id)
            ->delete_many();
    }

    private function groupAccess($groupname, $album_name) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $album = ORM::for_table(self::$prefix.'categories')->where('name', $album_name)->find_one();
        if (!$album) {
            throw new Exception('Album with name '.$album_name.' does not exist'."\n");
        }

        $group = ORM::for_table(self::$prefix.'groups')->where('name', $groupname)->find_one();
        if (!$group) {
            throw new Exception('Group with name '.$groupname.' does not exist'."\n");
        }

        $access = ORM::for_table(self::$prefix.'group_access')
            ->where('group_id', $group->id)
            ->where('cat_id', $album->id)
            ->find_one();
        if (!$access) {
            $access = ORM::for_table(self::$prefix.'group_access')->create();
            $access->group_id = $group->id;
            $access->cat_id = $album->id;
            $access->save();
        }
    }

    private function addComment($content, $photo_name, $username) {
        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $image = ORM::for_table(self::$prefix.'images')->where('name', $photo_name)->find_one();
        if (!$image) {
            throw new Exception('Image with name '.$photo_name.' does not exist'."\n");
        }
        $user = ORM::for_table(self::$prefix.'users')->where('username', $username)->find_one();
        if (!$user) {
            throw new Exception('User with username '.$username.' does not exist'."\n");
        }
        $comment = ORM::for_table(self::$prefix.'comments')->create();
        $comment->image_id = $image->id;
        $comment->author = $username;
        $comment->author_id = $user->id;
        $comment->content = $content;
        $yesterday = (new DateTime('now - 1 day'))->format('Y-m-d H:i:s');
        $comment->date = $yesterday;
        $comment->validation_date = $yesterday;
        $comment->validated = true;
        $comment->save();

        return $comment->id;
    }

    private function addTag($tag_name) {
        if (!defined('PHPWG_ROOT_PATH')) {
            define('PHPWG_ROOT_PATH', __DIR__.'/../../');
        }
        include_once(PHPWG_ROOT_PATH.'include/functions.inc.php');

        if (!self::$conf_loaded) {
            self::configDB($this->parameters);
        }

        $tag = ORM::for_table(self::$prefix.'tags')->where('name', $tag_name)->find_one();
        if (!$tag) {
            $tag = ORM::for_table(self::$prefix.'tags')->create();
            $tag->name = $tag_name;
            $tag->url_name = str2url($tag_name);
            $tag->save();
        }

        return $tag->id;
    }

    private function dissociateTags($param_tags, $image_id, $validated=true) {
        if (preg_match('`\[(.*)]`', $param_tags, $matches)) {
            $tags = array_map('trim', explode(',',  $matches[1]));
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
        foreach ($tags as $tag_name) {
            $tag_id = $this->addTag($tag_name);
            $image_tag = ORM::for_table(self::$prefix.'image_tag')
                ->where('tag_id', $tag_id)
                ->where('image_id', $image_id)
                ->find_one();
            if ($image_tag) {
                if (!$validated) {
                    $image_tag->status = 0;
                    $image_tag->validated = $validated;
                    $image_tag->save();
                } else {
                    $image_tag->delete();
                }
            }
        }
    }

    private function addTags($param_tags, $image_id, $validated=true) {
        if (preg_match('`\[(.*)]`', $param_tags, $matches)) {
            $tags = array_map('trim', explode(',',  $matches[1]));
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

        foreach ($tags as $tag_name) {
            $tag_id = $this->addTag($tag_name);
            $image_tag = ORM::for_table(self::$prefix.'image_tag')
                ->where('tag_id', $tag_id)
                ->where('image_id', $image_id)
                ->find_one();
            if (!$image_tag) {
                $image_tag = ORM::for_table(self::$prefix.'image_tag')->create();
                $image_tag->image_id = $image_id;
                $image_tag->tag_id = $tag_id;
                $image_tag->validated = $validated;
                $image_tag->status = 1;
                $image_tag->save();
            }
        }
    }
}
