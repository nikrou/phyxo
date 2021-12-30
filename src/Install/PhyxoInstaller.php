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

namespace App\Install;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Phyxo\Functions\Utils;
use Phyxo\Language\Languages;
use Phyxo\Upgrade;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhyxoInstaller
{
    private $phyxoVersion, $rootProjectDir, $translationsDir, $defaultTheme, $databaseYamlFile, $translator;
    private $default_prefix = 'phyxo_';

    private $dblayers = [
        'mysql' => [
            'engine' => 'MySQL, MariaDB, Percona Server, ...',
            'function_available' => 'mysqli_connect'
        ],

        'pgsql' => [
            'engine' => 'PostgreSQL',
            'function_available' => 'pg_connect'
        ],

        'sqlite' => [
            'engine' => 'SQLite',
            'class_available' => 'PDO'
        ]
    ];

    public function __construct(string $phyxoVersion, string $rootProjectDir, string $translationsDir, string $defaultTheme, string $databaseYamlFile, TranslatorInterface $translator)
    {
        $this->phyxoVersion = $phyxoVersion;
        $this->rootProjectDir = $rootProjectDir;
        $this->translationsDir = $translationsDir;
        $this->defaultTheme = $defaultTheme;
        $this->databaseYamlFile = $databaseYamlFile;
        $this->translator = $translator;
    }

    public function availableEngines()
    {
        return array_map(
            function($dblayer) {
                return $dblayer['engine'];
            },
            array_filter($this->dblayers, function($dblayer) {
                return (isset($dblayer['function_available']) && function_exists($dblayer['function_available']))
                || (isset($dblayer['class_available']) && class_exists($dblayer['class_available']));
            })
        );
    }

    public function installDatabase(array $db_params = [])
    {
        $config = new Configuration();
        if (!empty($db_params['dsn'])) {
            $_params = parse_url($db_params['dsn']);
            $db_params['db_layer'] = $_params['scheme'];
            $db_params['db_host'] = isset($_params['host']) ? $_params['host'] : '';
            $db_params['db_user'] = isset($_params['user']) ? $_params['user'] : '';
            $db_params['db_password'] = isset($_params['pass']) ? $_params['pass'] : '';
            $db_params['db_name'] = substr($_params['path'], 1);
            unset($_params);
        }

        $sqlite_db = sprintf('%s/db/%s', $this->rootProjectDir, $db_params['db_name']);
        if ($db_params['db_layer'] === 'sqlite') {
            $fs = new Filesystem();
            $fs->touch($sqlite_db);
            $fs->chmod($sqlite_db, 0666);
        }

        $connectionParams = [
            'dbname' => $db_params['db_name'],
            'user' => $db_params['db_user'],
            'password' => $db_params['db_password'],
            'host' => $db_params['db_host'],
            'driver' => 'pdo_' . $db_params['db_layer'],
        ];
        if ($db_params['db_layer'] === 'sqlite') {
            $connectionParams['path'] = $sqlite_db;
        }
        $conn = DriverManager::getConnection($connectionParams, $config);

        // tables creation, based on phyxo_structure.sql
        $structure_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/phyxo_structure-' . $db_params['db_layer'] . '.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        foreach ($structure_queries as $query) {
            $conn->executeQuery($query);
        }

        // We fill the tables with basic informations
        $config_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/config.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        foreach ($config_queries as $query) {
            $conn->executeQuery($query);
        }

        $raw_query = 'INSERT INTO phyxo_config (param, type, value, comment) VALUES(:param, :type, :value, :comment)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);

        $statement->bindValue('param', 'secret_key');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', md5(random_bytes(15)));
        $statement->bindValue('comment', 'a secret key specific to the gallery for internal use');
        $statement->executeStatement();

        $statement->bindValue('param', 'phyxo_db_version');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', Utils::get_branch_from_version($this->phyxoVersion));
        $statement->bindValue('comment', '');
        $statement->executeStatement();

        $statement->bindValue('param', 'gallery_title');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', $this->translator->trans('Just another Phyxo gallery', [], 'install'));
        $statement->bindValue('comment', '');
        $statement->executeStatement();

        $statement->bindValue('param', 'page_banner');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', '<h1>%gallery_title%</h1><p>' . $this->translator->trans('Welcome to my photo gallery', [], 'install') . '</p>');
        $statement->bindValue('comment', '');
        $statement->executeStatement();

        $raw_query = 'INSERT INTO phyxo_languages (id, version, name) VALUES(:id, :version, :name)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);

        $languages = new Languages();
        $languages->setRootPath($this->translationsDir);
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $statement->bindValue('id', $language_code);
            $statement->bindValue('version', $fs_language['version']);
            $statement->bindValue('name', $fs_language['name']);
            $statement->executeStatement();
        }

        // activate default theme
        $raw_query = 'INSERT INTO phyxo_themes (id, version, name) VALUES(:id, :version, :name)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);
        $statement->bindValue('id', $this->defaultTheme);
        $statement->bindValue('version', $this->phyxoVersion);
        $statement->bindValue('name', $this->defaultTheme);
        $statement->executeStatement();

        // Available upgrades must be ignored after a fresh installation.
        // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
        $raw_query = 'INSERT INTO phyxo_upgrade (id, applied, description) VALUES(:id, :applied, :description)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);
        $now = new \DateTime();

        foreach (Upgrade::getAvailableUpgradeIds($this->rootProjectDir) as $upgrade_id) {
            $statement->bindValue('id', $upgrade_id);
            $statement->bindValue('applied', $now->format('Y-m-d H:i:s'));
            $statement->bindValue('description', 'upgrade included in installation');
            $statement->executeStatement();
        }

        $file_content = 'parameters:' . "\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= '  database_driver: \'pdo_' . $db_params['db_layer'] . "'\n";
            $file_content .= '  database_host: \'' . $db_params['db_host'] . "'\n";
            $file_content .= '  database_name: \'' . $db_params['db_name'] . "'\n";
            $file_content .= '  database_user: \'' . $db_params['db_user'] . "'\n";
            $file_content .= '  database_password: \'' . $db_params['db_password'] . "'\n";
        } else {
            $file_content .= '  env(DATABASE_URL): "sqlite:///%kernel.project_dir%/db/' . $db_params['db_name'] . "\"\n";
        }
        $file_content .= '  database_prefix: \'' . $db_params['db_prefix'] . "'\n\n";

        file_put_contents($this->databaseYamlFile . '.tmp', $file_content);
        if (!is_readable($this->databaseYamlFile . '.tmp')) {
            throw new \Exception($this->translator->trans('Cannot create database configuration file "{filename}"', ['filename' => $this->databaseYamlFile], 'install'));
        }
    }

    /**
     * Returns queries from an SQL file.
     * Before returting a query, $replaced is... replaced by $replacing. This is
     * useful when the SQL file contains generic words. Drop table queries are not returned
     */
    protected function getQueriesFromFile(string $dblayer, string $filepath, string $replaced, string $replacing): array
    {
        $queries = [];

        $sql_lines = file($filepath);
        $query = '';
        foreach ($sql_lines as $sql_line) {
            $sql_line = trim($sql_line);
            if (preg_match('/(^--|^$)/', $sql_line)) {
                continue;
            }
            $query .= ' ' . $sql_line;
            // if we reached the end of query, we execute it and reinitialize the variable "query"
            if (preg_match('/;$/', $sql_line)) {
                $query = trim($query);
                $query = str_replace($replaced, $replacing, $query);
                // we don't execute "DROP TABLE" queries
                if (!preg_match('/^DROP TABLE/i', $query)) {
                    if ($dblayer === 'mysql') {
                        if (preg_match('/^(CREATE TABLE .*)[\s]*;[\s]*/im', $query, $matches)) {
                            $query = $matches[1] . ' DEFAULT CHARACTER SET utf8' . ';';
                        }
                    }
                    $queries[] = $query;
                }
                $query = '';
            }
        }

        return $queries;
    }
}
