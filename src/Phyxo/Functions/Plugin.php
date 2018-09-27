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

use App\Repository\PluginRepository;

class Plugin
{
    /** default priority for plugins handlers */
    const EVENT_HANDLER_PRIORITY_NEUTRAL = 50;

    /**
     * Register an event handler.
     *
     * @param string $event the name of the event to listen to
     * @param Callable $func the callback function
     * @param int $priority greater priority will be executed at last
     * @param string $include_path file to include before executing the callback
     * @return bool false if handler already exists
     */
    public static function add_event_handler($event, $func, $priority = self::EVENT_HANDLER_PRIORITY_NEUTRAL, $include_path = null)
    {
        global $pwg_event_handlers;

        if (isset($pwg_event_handlers[$event][$priority])) {
            foreach ($pwg_event_handlers[$event][$priority] as $handler) {
                if ($handler['function'] == $func) {
                    return false;
                }
            }
        }

        $pwg_event_handlers[$event][$priority][] = [
            'function' => $func,
            'include_path' => is_string($include_path) ? $include_path : null,
        ];

        ksort($pwg_event_handlers[$event]);
        return true;
    }

    /**
     * Removes an event handler.
     * @see add_event_handler()
     *
     * @param string $event
     * @param Callable $func
     * @param int $priority
     */
    public static function remove_event_handler($event, $func, $priority = self::EVENT_HANDLER_PRIORITY_NEUTRAL)
    {
        global $pwg_event_handlers;

        if (!isset($pwg_event_handlers[$event][$priority])) {
            return false;
        }

        for ($i = 0; $i < count($pwg_event_handlers[$event][$priority]); $i++) {
            if ($pwg_event_handlers[$event][$priority][$i]['function'] == $func) {
                unset($pwg_event_handlers[$event][$priority][$i]);
                $pwg_event_handlers[$event][$priority] = array_values($pwg_event_handlers[$event][$priority]);

                if (empty($pwg_event_handlers[$event][$priority])) {
                    unset($pwg_event_handlers[$event][$priority]);
                    if (empty($pwg_event_handlers[$event])) {
                        unset($pwg_event_handlers[$event]);
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Triggers a modifier event and calls all registered event handlers.
     * trigger_change() is used as a modifier: it allows to transmit _$data_
     * through all handlers, thus each handler MUST return a value,
     * optional _$args_ are not transmitted.
     *
     * @param string $event
     * @param mixed $data data to transmit to all handlers
     * @param mixed $args,... optional arguments
     * @return mixed $data
     */
    public static function trigger_change($event, $data = null)
    {
        global $pwg_event_handlers;

        if (isset($pwg_event_handlers['trigger'])) {// debugging
            self::trigger_notify(
                'trigger',
                ['type' => 'event', 'event' => $event, 'data' => $data]
            );
        }

        if (!isset($pwg_event_handlers[$event])) {
            return $data;
        }
        $args = func_get_args();
        array_shift($args);

        foreach ($pwg_event_handlers[$event] as $priority => $handlers) {
            foreach ($handlers as $handler) {
                $args[0] = $data;

                if (!empty($handler['include_path'])) {
                    include_once($handler['include_path']);
                }

                $data = call_user_func_array($handler['function'], $args);
            }
        }

        if (isset($pwg_event_handlers['trigger'])) { // debugging
            self::trigger_notify(
                'trigger',
                ['type' => 'post_event', 'event' => $event, 'data' => $data]
            );
        }

        return $data;
    }

    /**
     * Triggers a notifier event and calls all registered event handlers.
     * trigger_notify() is only used as a notifier, no modification of data is possible
     *
     *
     * @param string $event
     * @param mixed $args,... optional arguments
     */
    public static function trigger_notify($event)
    {
        global $pwg_event_handlers;

        if (isset($pwg_event_handlers['trigger']) and $event != 'trigger') { // debugging - avoid recursive calls
            self::trigger_notify(
                'trigger',
                ['type' => 'action', 'event' => $event, 'data' => null]
            );
        }

        if (!isset($pwg_event_handlers[$event])) {
            return;
        }
        $args = func_get_args();
        array_shift($args);

        foreach ($pwg_event_handlers[$event] as $priority => $handlers) {
            foreach ($handlers as $handler) {
                if (!empty($handler['include_path'])) {
                    include_once($handler['include_path']);
                }

                call_user_func_array($handler['function'], $args);
            }
        }
    }

    /**
     * Loads a plugin in memory.
     * It performs autoupdate, includes the main.inc.php file and updates *$pwg_loaded_plugins*.
     *
     * @param string $plugin
     */
    public static function load_plugin($plugin)
    {
        $file_name = PHPWG_PLUGINS_PATH . '/' . $plugin['id'] . '/main.inc.php';
        if (file_exists($file_name)) {
            self::autoupdate_plugin($plugin);
            global $pwg_loaded_plugins;
            $pwg_loaded_plugins[$plugin['id']] = $plugin;
            include_once($file_name);
        }
    }

    /**
     * Loads all the registered plugins.
     */
    public static function load_plugins()
    {
        global $conf, $pwg_loaded_plugins, $conn;

        $pwg_loaded_plugins = [];

        if ($conf['enable_plugins']) {
            $plugins = new \Phyxo\Plugin\Plugins($conn);
            $db_plugins = $plugins->getDbPlugins('active');
            foreach ($db_plugins as $plugin) { // include main from a function to avoid using same function context
                self::load_plugin($plugin);
            }
        }
        self::trigger_notify('plugins_loaded');
    }

    /**
     * Performs update task of a plugin.
     * Autoupdate is only performed if the plugin has a maintain.class.php file.
     *
     * @param array &$plugin (id, version, state) will be updated if version changes
     */
    public static function autoupdate_plugin(&$plugin)
    {
        global $conn;

        // try to find the filesystem version in lines 2 to 10 of main.inc.php
        $fh = fopen(PHPWG_PLUGINS_PATH . '/' . $plugin['id'] . '/main.inc.php', 'r');
        $fs_version = null;
        $i = -1;

        while (($line = fgets($fh)) !== false && $fs_version == null && $i < 10) {
            $i++;
            if ($i < 2) {
                continue; // first lines are typically "<?php" and "/*"
            }

            if (preg_match('/Version:\\s*([\\w.-]+)/', $line, $matches)) {
                $fs_version = $matches[1];
            }
        }
        fclose($fh);

        // if version is auto (dev) or superior
        if ($fs_version != null && ($fs_version == 'auto' || $plugin['version'] == 'auto' ||
            version_compare($plugin['version'], $fs_version, '<'))) {
            $plugin['version'] = $fs_version;

            $maintain_file = PHPWG_PLUGINS_PATH . '/' . $plugin['id'] . '/maintain.class.php';

            // autoupdate is applicable only to plugins with 2.7 architecture
            if (file_exists($maintain_file)) {
                global $page;

                // call update method
                include_once($maintain_file);

                $classname = $plugin['id'] . '_maintain';
                $plugin_maintain = new $classname($plugin['id']);
                $plugin_maintain->update($plugin['version'], $fs_version, $page['errors']);
            }

            // update database (only on production)
            if ($plugin['version'] != 'auto') {
                (new PluginRepository($conn))->updatePlugin(['version' => $plugin['version']], ['id' => $plugin['id']]);
            }
        }
    }

    /**
     * Retrieves an url for a plugin page.
     * @param string file - php script full name
     */
    public static function get_admin_plugin_menu_link($file)
    {
        global $page;
        $real_file = realpath($file);
        $url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=plugin';
        if (false !== $real_file) {
            $real_plugin_path = rtrim(realpath(PHPWG_PLUGINS_PATH), '\\/');
            $file = substr($real_file, strlen($real_plugin_path) + 1);
            $file = str_replace('\\', '/', $file);//Windows
            $url .= '&amp;section=' . urlencode($file);
        } elseif (isset($page['errors'])) {
            $page['errors'][] = 'PLUGIN ERROR: "' . $file . '" is not a valid file';
        }

        return $url;
    }
}
