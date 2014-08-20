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
     */
    public function aUser(TableNode $table) {
        $params = array(
            'username' => '',
            'password' => '',
            'status' => 'normal'
        );
        foreach ($table->getRowsHash() as $key => $value) {
            $params[$key] = $value;
        }
        self::addUser($params['username'], $params['password'], $params['status']);
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

        // primary keys
        ORM::configure(
            'id_column_overrides',
            array(self::$prefix.'user_infos' => 'user_id')
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
            include_once(__DIR__.'/../../include/passwordhash.class.php');
            $pwg_hasher = new PasswordHash(13, true);
            $user = ORM::for_table(self::$prefix.'users')->create();
            $user->username = $username;
            $user->password = $pwg_hasher->HashPassword($password);
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
}