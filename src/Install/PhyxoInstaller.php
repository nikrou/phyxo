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

use DateTime;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Exception;
use PDO;
use Phyxo\Functions\Utils;
use Phyxo\Language\Languages;
use Phyxo\Upgrade;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhyxoInstaller
{
    private string $localEnvFile = '';

    /** @var array<string, array{engine:string, function_available?:string, class_available?:string}> */
    private array $dblayers = [
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

    public function __construct(
        private readonly string $phyxoVersion,
        private readonly string $rootProjectDir,
        private readonly string $translationsDir,
        private readonly string $defaultTheme,
        private readonly string $prefix,
        private readonly TranslatorInterface $translator
    ) {
        $this->localEnvFile = sprintf('%s/.env.local', $this->rootProjectDir);
    }

    /**
     * @return array<string, string>
     */
    public function availableEngines(): array
    {
        return array_map(
            fn ($dblayer): string => $dblayer['engine'],
            array_filter($this->dblayers, fn ($dblayer): bool => (isset($dblayer['function_available']) && function_exists($dblayer['function_available']))
            || (isset($dblayer['class_available']) && class_exists($dblayer['class_available'])))
        );
    }

    /**
     * @param array<string, mixed> $db_params
     */
    public function installDatabase(array $db_params = []): void
    {
        $config = new Configuration();
        if (!empty($db_params['dsn'])) {
            $_params = parse_url((string) $db_params['dsn']);
            $db_params['db_layer'] = $_params['scheme'];
            $db_params['db_host'] = $_params['host'] ?? '';
            $db_params['db_user'] = $_params['user'] ?? '';
            $db_params['db_password'] = $_params['pass'] ?? '';
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
            'path' => ''
        ];
        if ($db_params['db_layer'] === 'sqlite') {
            $connectionParams['path'] = $sqlite_db;
        }

        $conn = DriverManager::getConnection($connectionParams, $config);

        // tables creation, based on phyxo_structure.sql
        $structure_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/phyxo_structure-' . $db_params['db_layer'] . '.sql',
            $this->prefix,
            $db_params['db_prefix']
        );
        foreach ($structure_queries as $query) {
            $conn->executeQuery($query);
        }

        // We fill the tables with basic informations
        $config_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/config.sql',
            $this->prefix,
            $db_params['db_prefix']
        );
        foreach ($config_queries as $query) {
            $conn->executeQuery($query);
        }

        $raw_query = 'INSERT INTO phyxo_config (param, type, value, comment) VALUES(:param, :type, :value, :comment)';
        $raw_query = str_replace($this->prefix, $db_params['db_prefix'], $raw_query);

        $statement = $conn->prepare($raw_query);

        $statement->bindValue('param', 'secret_key');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', md5(random_bytes(15)));
        $statement->bindValue('comment', 'a secret key specific to the gallery for internal use');
        $statement->executeStatement();

        $statement->bindValue('param', 'phyxo_db_version');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', Utils::getBranchFromVersion($this->phyxoVersion));
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
        $raw_query = str_replace($this->prefix, $db_params['db_prefix'], $raw_query);

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
        $raw_query = str_replace($this->prefix, $db_params['db_prefix'], $raw_query);

        $statement = $conn->prepare($raw_query);
        $statement->bindValue('id', $this->defaultTheme);
        $statement->bindValue('version', $this->phyxoVersion);
        $statement->bindValue('name', $this->defaultTheme);
        $statement->executeStatement();

        // Available upgrades must be ignored after a fresh installation.
        // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
        $raw_query = 'INSERT INTO phyxo_upgrade (id, applied, description) VALUES(:id, :applied, :description)';
        $raw_query = str_replace($this->prefix, $db_params['db_prefix'], $raw_query);

        $statement = $conn->prepare($raw_query);
        $now = new DateTime();

        foreach (Upgrade::getAvailableUpgradeIds($this->rootProjectDir) as $upgrade_id) {
            $statement->bindValue('id', $upgrade_id);
            $statement->bindValue('applied', $now->format('Y-m-d H:i:s'));
            $statement->bindValue('description', 'upgrade included in installation');
            $statement->executeStatement();
        }

        $file_content = "\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= sprintf(
                'DATABASE_URL=%s://%s:%s@%s/%s?serverVersion=%d' . "\n",
                $db_params['db_layer'],
                $db_params['db_user'],
                urlencode((string) $db_params['db_password']),
                $db_params['db_host'],
                $db_params['db_name'],
                /** @phpstan-ignore-next-line */
                $conn->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION)
            );
        } else {
            $file_content .= sprintf('DATABASE_URL="sqlite:///%s/db/%s"' . "\n", $this->rootProjectDir, $db_params['db_name']);
        }

        $file_content .= "DATABASE_PREFIX='" . $db_params['db_prefix'] . "'\n\n";

        $temporayFile = $this->localEnvFile . '.tmp';
        file_put_contents($temporayFile, $file_content, FILE_APPEND);
        if (!is_readable($temporayFile)) {
            throw new Exception($this->translator->trans('Cannot create database configuration file "{filename}"', ['filename' => $temporayFile], 'install'));
        }
    }

    /**
     * Returns queries from an SQL file.
     * Before returting a query, $replaced is... replaced by $replacing. This is
     * useful when the SQL file contains generic words. Drop table queries are not returned
     *
     * @return array<string>
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
                    if ($dblayer === 'mysql' && preg_match('/^(CREATE TABLE .*)[\s]*;[\s]*/im', $query, $matches)) {
                        $query = $matches[1] . ' DEFAULT CHARACTER SET utf8' . ';';
                    }

                    $queries[] = $query;
                }

                $query = '';
            }
        }

        return $queries;
    }
}
