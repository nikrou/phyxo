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

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Phyxo\DBLayer\DBLayer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Phyxo\Language\Languages;
use Phyxo\Upgrade;
use Phyxo\Functions\Utils;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;

class InstallCommand extends Command
{
    protected static $defaultName = 'phyxo:install';

    private $db_params = ['db_layer' => '', 'db_host' => '', 'db_name' => '', 'db_user' => '', 'db_password' => '', 'db_prefix' => ''];
    private $phyxoVersion, $databaseConfigFile, $databaseYamlFile, $rootProjectDir, $translationsDir, $default_theme;
    private $default_prefix = 'phyxo_';

    public function __construct(string $phyxoVersion, string $databaseConfigFile, string $databaseYamlFile, string $rootProjectDir, string $translationsDir, string $defaultTheme)
    {
        parent::__construct();

        $this->phyxoVersion = $phyxoVersion;
        $this->databaseConfigFile = $databaseConfigFile;
        $this->databaseYamlFile = $databaseYamlFile;
        $this->rootProjectDir = $rootProjectDir;
        $this->translationsDir = $translationsDir;
        $this->default_theme = $defaultTheme;
    }

    public function isEnabled()
    {
        return !is_readable($this->databaseConfigFile);
    }

    public function configure()
    {
        $this
            ->setDescription("Install Phyxo")
            ->setHelp(file_get_contents(__DIR__ . '/../Resources/help/InstallCommand.txt'))

            ->addOption('db_layer', null, InputOption::VALUE_REQUIRED, 'Database type')
            ->addOption('db_host', null, InputOption::VALUE_OPTIONAL, 'Database hostname')
            ->addOption('db_user', null, InputOption::VALUE_OPTIONAL, 'Database username')
            ->addOption('db_password', null, InputOption::VALUE_OPTIONAL, 'Database password')
            ->addOption('db_name', null, InputOption::VALUE_REQUIRED, 'Database name')
            ->addOption('db_prefix', null, InputOption::VALUE_REQUIRED, 'Database prefix for tables', (string) $this->default_prefix);
    }

    public function interact(InputInterface $input, OutputInterface $output)
    {
        if (!empty($_SERVER['DATABASE_URL'])) {
            return;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('Phyxo installation');

        $io->section('Database settings');
        $dbengines = DBLayer::availableEngines();
        if (($this->db_params['db_layer'] = $input->getOption('db_layer')) === null) {
            $this->db_params['db_layer'] = $io->choice('Select database type', $dbengines, array_values($dbengines)[0]);
            $input->setOption('db_layer', $this->db_params['db_layer']);
        } else {
            $io->text(sprintf('<info>Database type is:</info> %s (%s)', $dbengines[$this->db_params['db_layer']], $this->db_params['db_layer']));
        }

        if ($this->db_params['db_layer'] !== 'sqlite') {
            if (($this->db_params['db_host'] = $input->getOption('db_host')) === null) {
                $this->db_params['db_host'] = $io->ask('Database hostname', 'localhost');
                $input->setOption('db_host', $this->db_params['db_host']);
            } else {
                $io->text(sprintf('<info>Database hostname is:</info> %s', $this->db_params['db_host']));
            }

            if (($this->db_params['db_user'] = $input->getOption('db_user')) === null) {
                $this->db_params['db_user'] = $io->ask('Database username', null, function($user) {
                    if (empty($user)) {
                        throw new \Exception('Database username cannot be empty.');
                    }

                    return $user;
                });
                $input->setOption('db_user', $this->db_params['db_user']);
            } else {
                $io->text(sprintf('<info>Database username is:</info> %s', $this->db_params['db_user']));
            }

            if (($this->db_params['db_password'] = $input->getOption('db_password')) === null) {
                $this->db_params['db_password'] = $io->askHidden('Database password');
                $input->setOption('db_password', $this->db_params['db_password']);
            } else {
                $io->text(sprintf('<info>Database password is:</info> %s', $io->isVerbose() ? $this->db_params['db_password']: '****'));
            }
        }

        if (($this->db_params['db_name'] = $input->getOption('db_name')) === null) {
            $this->db_params['db_name'] = $io->ask('Database name');
            $input->setOption('db_name', $this->db_params['db_name']);
        } else {
            $io->text(sprintf('<info>Database name is:</info> %s', $this->db_params['db_name']));
        }

        if (($this->db_params['db_prefix'] = $input->getOption('db_prefix')) === null) {
            $this->db_params['db_prefix'] = $io->ask('Database prefix for tables', $input->getOption('db_prefix'));
            $input->setOption('db_prefix', $this->db_params['db_prefix']);
        } else {
            $io->text(sprintf('<info>Database prefix is:</info> %s', $this->db_params['db_prefix']));
        }
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!empty($_SERVER['DATABASE_URL'])) {
            $this->db_params['dsn'] = $_SERVER['DATABASE_URL'];
            $this->db_params['db_prefix'] = $input->getOption('db_prefix') ? $input->getOption('db_prefix') : $this->default_prefix;
        } else {
            if (!$io->askQuestion(new ConfirmationQuestion("Install Phyxo using these settings?"), true)) {
                return;
            }
        }

        try {
            $this->installDatabase($this->db_params);

            rename($this->databaseConfigFile . '.tmp', $this->databaseConfigFile);
            rename($this->databaseYamlFile . '.tmp', $this->databaseYamlFile);

            $env_file_content = 'APP_ENV=prod' . "\n";
            $env_file_content .= 'APP_SECRET=' . hash('sha256', openssl_random_pseudo_bytes(50)) . "\n";
            file_put_contents($this->rootProjectDir . '/.env.local', $env_file_content, FILE_APPEND);

            // clear cache
            $command = $this->getApplication()->find('cache:clear');
            $arguments = [
                'command' => 'cache:clear',
                '--no-warmup' => true,
            ];
            $output = new NullOutput();

            return $command->run(new ArrayInput($arguments), $output);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return 1;
        }

        $this->writeInfo($io, "What's next?");
        $io->listing([
            'Create a webmaster, using phyxo:user:create command',
            'Create a guest user, using phyxo:user:create command',
            'Go to http://your.hostname/path/to/phyxo and start using your application'
        ]);
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

    protected function installDatabase(array $db_params = [])
    {
        $config = new \Doctrine\DBAL\Configuration();
        if (!empty($db_params['dsn'])) {
            $_params = parse_url($db_params['dsn']);
            $db_params['db_layer'] = $_params['scheme'];
            $db_params['db_host'] = isset($_params['host']) ? $_params['host'] : '';
            $db_params['db_user'] = isset($_params['user']) ? $_params['user'] : '';
            $db_params['db_password'] = isset($_params['pass']) ? $_params['pass'] : '';
            $db_params['db_name'] = substr($_params['path'], 1);
            unset($_params);
        }
        $connectionParams = [
            'dbname' => $db_params['db_name'],
            'user' => $db_params['db_user'],
            'password' => $db_params['db_password'],
            'host' => $db_params['db_host'],
            'driver' => 'pdo_' . $db_params['db_layer'],
        ];

        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

        // tables creation, based on phyxo_structure.sql
        $structure_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/phyxo_structure-' . $db_params['db_layer'] . '.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        foreach ($structure_queries as $query) {
            $conn->query($query);
        }

        // We fill the tables with basic informations
        $config_queries = $this->getQueriesFromFile(
            $db_params['db_layer'],
            $this->rootProjectDir . '/install/config.sql',
            $this->default_prefix,
            $db_params['db_prefix']
        );
        foreach ($config_queries as $query) {
            $conn->query($query);
        }

        $raw_query = 'INSERT INTO phyxo_config (param, type, value, comment) VALUES(:param, :type, :value, :comment)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);

        $statement->bindValue('param', 'secret_key');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', md5(random_bytes(15)));
        $statement->bindValue('comment', 'a secret key specific to the gallery for internal use');
        $statement->execute();

        $statement->bindValue('param', 'phyxo_db_version');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', Utils::get_branch_from_version($this->phyxoVersion));
        $statement->bindValue('comment', '');
        $statement->execute();

        $statement->bindValue('param', 'gallery_title');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', 'Just another Phyxo gallery');
        $statement->bindValue('comment', '');
        $statement->execute();

        $statement->bindValue('param', 'page_banner');
        $statement->bindValue('type', 'string');
        $statement->bindValue('value', '<h1>%gallery_title%</h1><p>Welcome to my photo gallery</p>');
        $statement->bindValue('comment', '');
        $statement->execute();

        $raw_query = 'INSERT INTO phyxo_languages (id, version, name) VALUES(:id, :version, :name)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);

        $languages = new Languages();
        $languages->setRootPath($this->translationsDir);
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $statement->bindValue('id', $language_code);
            $statement->bindValue('version', $fs_language['version']);
            $statement->bindValue('name', $fs_language['name']);
            $statement->execute();
        }

        // activate default theme
        $raw_query = 'INSERT INTO phyxo_themes (id, version, name) VALUES(:id, :version, :name)';
        $raw_query = str_replace($this->default_prefix, $db_params['db_prefix'], $raw_query);
        $statement = $conn->prepare($raw_query);
        $statement->bindValue('id', $this->default_theme);
        $statement->bindValue('version', $this->phyxoVersion);
        $statement->bindValue('name', $this->default_theme);
        $statement->execute();

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
            $statement->execute();
        }

        $file_content = '<?php' . "\n";
        $file_content .= '$conf[\'dblayer\'] = \'' . $db_params['db_layer'] . "';\n";
        $file_content .= '$conf[\'db_base\'] = \'' . $db_params['db_name'] . "';\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= '$conf[\'db_host\'] = \'' . $db_params['db_host'] . "';\n";
            $file_content .= '$conf[\'db_user\'] = \'' . $db_params['db_user'] . "';\n";
            $file_content .= '$conf[\'db_password\'] = \'' . $db_params['db_password'] . "';\n";
        }
        $file_content .= '$conf[\'db_prefix\'] = \'' . $db_params['db_prefix'] . "';\n\n";

        file_put_contents($this->databaseConfigFile . '.tmp', $file_content);

        $file_content = 'parameters:' . "\n";
        $file_content .= '  database_driver: \'pdo_' . $db_params['db_layer'] . "'\n";
        $file_content .= '  database_name: \'' . $db_params['db_name'] . "'\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= '  database_host: \'' . $db_params['db_host'] . "'\n";
            $file_content .= '  database_user: \'' . $db_params['db_user'] . "'\n";
            $file_content .= '  database_password: \'' . $db_params['db_password'] . "'\n";
        }
        $file_content .= '  database_prefix: \'' . $db_params['db_prefix'] . "'\n\n";
        file_put_contents($this->databaseYamlFile . '.tmp', $file_content);
    }

    protected function writeInfo($io, string $message)
    {
        $io->newLine();
        $io->writeln('<bg=blue;fg=white> ' . str_pad('', strlen($message), ' ') . ' </>');
        $io->writeln(sprintf('<bg=blue;fg=white> %s </>', $message));
        $io->writeln('<bg=blue;fg=white> ' . str_pad('', strlen($message), ' ') . ' </>');
        $io->newLine();
    }
}
