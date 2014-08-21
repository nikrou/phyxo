<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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

    /**
     * @Given /^a user:$/
     * @Given /^some users:$/
     */
    public function aUser(TableNode $table) {
        $params = array(
            'username' => '',
            'password' => '',
            'status' => 'normal'
        );
        foreach ($table->getHash() as $user) {
            $params['username'] = $user['username'];
            $params['password'] = $user['password'];
            $params['status'] = $user['status'];
        }
        self::addUser($params['username'], $params['password'], $params['status']);
    }

    /**
     * @Given /^images:$/
     */
    public function images(TableNode $table) {
        $params = array(
            'name' => '',
            'album' => ''
        );
        foreach ($table->getHash() as $image) {
            $params['name'] = $image['name'];
            $params['album'] = $image['album'];
            self::addImage($params);
        }
    }

    /**
     * @Given /^an album:$/
     */
    public function anAlbum(TableNode $table) {
        $params = array('name' => '');
        foreach ($table->getRowsHash() as $key => $value) {
            $params[$key] = $value;
        }
        self::addAlbum($params['name']);
    }

    /**
     * @Given /^"([^"]*)" can access "([^"]*)"$/
     */
    public function canAccess($username, $album_name) {
        self::manageAccess($username, $album_name);
    }

    /**
     * @Given /^"([^"]*)" cannot access "([^"]*)"$/
     */
    public function cannotAccess($username, $album_name) {
        self::manageAccess($username, $album_name, $remove=true);
    }

    public function getAlbum($album_name) {
        $album = ORM::for_table(self::$prefix.'categories')->where('name', $album_name)->find_one();
        if (!$album) {
            throw new Exception('Album with name '.$album_name.' does not exist'."\n");
        }

        return $album;
    }

    /**
     * @BeforeSuite
     */
    public static function prepareDB(SuiteEvent $event) {
        $parameters = $event->getContextParameters();
        if (!empty($parameters['sql_init_file']) && !empty($parameters['config_file'])
        && is_readable($parameters['sql_init_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters['config_file']);
                self::$conf_loaded = true;
            }

            $sql_content = trim(file_get_contents($parameters['sql_init_file']));
            if (!empty($sql_content)) {
                ORM::get_db()->exec($sql_content);
            }
        }
    }

    /**
     * @AfterScenario
     */
    public static function cleanDB(ScenarioEvent $event) {
        $parameters = $event->getContext()->parameters;

        if (!empty($parameters['sql_cleanup_file']) && !empty($parameters['config_file'])
        && is_readable($parameters['sql_cleanup_file']) && is_readable($parameters['config_file'])) {
            if (!self::$conf_loaded) {
                self::configDB($parameters['config_file']);
                self::$conf_loaded = true;
            }

            $sql_content = trim(file_get_contents($parameters['sql_cleanup_file']));
            if (!empty($sql_content)) {
                ORM::get_db()->exec($sql_content);
            }
        }
    }

    private static function configDb($config_file) {
        include($config_file);
        self::$prefix = $prefixeTable;
        ORM::configure($conf['dblayer'].':host='.$conf['db_host'].';dbname='.$conf['db_base']);
        ORM::configure('username', $conf['db_user']);
        ORM::configure('password', $conf['db_password']);

        ORM::configure('logging', true);

        // primary keys
        ORM::configure(
            'id_column_overrides',
            array(
                self::$prefix.'user_infos' => 'user_id',
                self::$prefix.'image_category' => array('image_id', 'category_id'),
                self::$prefix.'user_access' => array('user_id', 'cat_id'),
            )
        );
    }

    private static function configApp() {
        $config = ORM::for_table(self::$prefix.'config')
            ->where('param', 'browser_language')
            ->find_one();
        if (!$config) {
            $config = ORM::for_table(self::$prefix.'config')->create();
            $config->param = 'browser_language';
            $config->value = 'false';
            $config->save();
        }
        $config = ORM::for_table(self::$prefix.'config')
            ->where('param', 'newcat_default_status')
            ->find_one();
        if (!$config) {
            $config = ORM::for_table(self::$prefix.'config')->create();
            $config->param = 'newcat_default_status';
            $config->value = 'private';
            $config->save();
        }
    }

    /* ORM methods */
    private static function addUser($username, $password, $status) {
        $user = ORM::for_table(self::$prefix.'users')->where('username', $username)->find_one();
        if (!$user) {
            $user = ORM::for_table(self::$prefix.'users')->create();
            $user->username = $username;
            $user->password = password_hash($password, PASSWORD_BCRYPT);
            $user->save();

            $user_info = ORM::for_table(self::$prefix.'user_infos')->create();
            $user_info->user_id = $user->id;
            $user_info->status = $status;
            $user_info->registration_date = 'now()';
            if ($status=='webmaster') {
                // to be retrieve from config_default
                // @see include/function_user.inc.php:create_user_infos
                $user_info->level = 8;
            } else {
                $user_info->level = 0;
            }
            $user_info->save();
        }
    }

    private static function addImage(array $params) {
        if (empty($params['album']) || empty($params['name'])) {
            throw new Exception('Album name and image name are mandatory'."\n");
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
        $upload_dir = './upload/'.$now->format('Y/m/d');
        $path = $upload_dir . sprintf('/%s-%s.png', $now->format('YmdHis'), substr($md5sum, 0, 8));

        $image->path = $path;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        copy($params['file'], $path);
        if (empty($params['date_creation'])) {
            $image->set_expr('date_creation', 'now()');
        } else {
            $image->date_creation = $params['date_creation'];
        }
        if (!empty($params['date_available'])) {
            $image->date_available = $params['date_available'];
        } else {
            $image->set_expr('date_available', 'now()');
        }
        $image->md5sum = $md5sum;
        $image->save();

        $image_category = ORM::for_table(self::$prefix.'image_category')->create();
        $image_category->image_id = $image->id;
        $image_category->category_id = $album->id;
        $image_category->save();

        return $image->id;
    }

    private static function addAlbum($name) {
        $album = ORM::for_table(self::$prefix.'categories')->where('name', $name)->find_one();
        if (!$album) {
            $album = ORM::for_table(self::$prefix.'categories')->create();
            $album->name = $name;
            $album->status = 'private';
            $album->comment = 'true';
            $album->global_rank = 1;
            $album->save();

            $album->uppercats = $album->id;
            $album->save();
        }
    }

    private static function manageAccess($username, $album_name, $remove=false) {
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
}