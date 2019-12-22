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
use Phyxo\Conf;
use App\Repository\ConfigRepository;
use App\Repository\UpgradeRepository;
use Phyxo\Language\Languages;
use App\Repository\BaseRepository;
use App\Repository\ThemeRepository;
use Phyxo\Upgrade;
use Phyxo\EntityManager;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;

class InstallCommand extends Command
{
    protected static $defaultName = 'phyxo:install';

    private $db_params = ['db_layer' => '', 'db_host' => '', 'db_name' => '', 'db_user' => '', 'db_password' => '', 'db_prefix' => ''];
    private $phyxoVersion, $defaultTheme;

    public function __construct(string $phyxoVersion, string $defaultTheme)
    {
        parent::__construct();

        $this->phyxoVersion = $phyxoVersion;
        $this->defaultTheme = $defaultTheme;
    }

    public function isEnabled()
    {
        return !is_readable($this->getApplication()->getKernel()->getDbConfigFile());
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
            ->addOption('db_prefix', null, InputOption::VALUE_REQUIRED, 'Database prefix for tables', DBLayer::DEFAULT_PREFIX);
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
            $this->db_params['db_prefix'] = $input->getOption('db_prefix') ? $input->getOption('db_prefix') : DBLayer::DEFAULT_PREFIX;
        } else {
            if (!$io->askQuestion(new ConfirmationQuestion("Install Phyxo using these settings?"), true)) {
                return;
            }
        }

        try {
            $this->installDatabase($this->db_params);

            rename($this->getApplication()->getKernel()->getDbConfigFile() . '.tmp', $this->getApplication()->getKernel()->getDbConfigFile());

            $env_file_content = 'APP_ENV=prod' . "\n";
            $env_file_content .= 'APP_SECRET=' . hash('sha256', openssl_random_pseudo_bytes(50)) . "\n";
            file_put_contents($this->getApplication()->getKernel()->getProjectDir() . '/.env', $env_file_content);

            // clear cache
            $command = $this->getApplication()->find('cache:clear');
            $arguments = [
                'command' => 'cache:clear',
                '--no-warmup' => true,
            ];
            $output = new NullOutput();
            $returnCode = $command->run(new ArrayInput($arguments), $output);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return;
        }

        $this->writeInfo($io, "What's next?");
        $io->listing([
            'Create a webmaster, using phyxo:user:create command',
            'Create a guest user, using phyxo:user:create command',
            'Go to http://your.hostname/path/to/phyxo and start using your application'
        ]);
    }

    protected function installDatabase(array $db_params = [])
    {
        if (!empty($db_params['dsn'])) {
            $conn = DBLayer::initFromDSN($db_params['dsn']);
        } else {
            $conn = DBLayer::init($db_params['db_layer'], $db_params['db_host'], $db_params['db_user'], $db_params['db_password'], $db_params['db_name'], $db_params['db_prefix']);
        }
        $em = new EntityManager($conn);

        // load configuration
        $conf = new Conf($conn);
        $conf->loadFromFile($this->getApplication()->getKernel()->getProjectDir() . '/include/config_default.inc.php');
        $conf->loadFromFile($this->getApplication()->getKernel()->getProjectDir() . '/local/config/config.inc.php');

        // tables creation, based on phyxo_structure.sql
        $conn->executeSqlFile(
            $this->getApplication()->getKernel()->getProjectDir() . '/install/phyxo_structure-' . $conn->getLayer() . '.sql',
            DBLayer::DEFAULT_PREFIX,
            $db_params['db_prefix']
        );
        // We fill the tables with basic informations
        $conn->executeSqlFile(
            $this->getApplication()->getKernel()->getProjectDir() . '/install/config.sql',
            DBLayer::DEFAULT_PREFIX,
            $db_params['db_prefix']
        );

        $em->getRepository(ConfigRepository::class)->addParam(
            'secret_key',
            md5(random_bytes(15)),
            '\'a secret key specific to the gallery for internal use\')'
        );

        $conf['phyxo_db_version'] = \Phyxo\Functions\Utils::get_branch_from_version($this->phyxoVersion);
        $conf['gallery_title'] = 'Just another Phyxo gallery';
        $conf['page_banner'] = '<h1>%gallery_title%</h1><p>' . 'Welcome to my photo gallery' . '</p>';

        $languages = new Languages($conn, null);
        $languages->setRootPath($this->getApplication()->getKernel()->getProjectDir() . '/language');
        foreach ($languages->getFsLanguages() as $language_code => $fs_language) {
            $languages->performAction('activate', $language_code);
        }

        // activate default theme
        (new ThemeRepository($conn))->addTheme('treflez', '0.1.0', 'Treflez');

        $conf->loadFromDB();

        // Available upgrades must be ignored after a fresh installation.
        // To make Phyxo avoid upgrading, we must tell it upgrades have already been made.
        $dbnow = $em->getRepository(BaseRepository::class)->getNow();
        $datas = [];
        $upgrade = new Upgrade($em, $conf);
        foreach ($upgrade->getAvailableUpgradeIds($this->getApplication()->getKernel()->getProjectDir()) as $upgrade_id) {
            $datas[] = [
                'id' => $upgrade_id,
                'applied' => $dbnow,
                'description' => 'upgrade included in installation',
            ];
        }
        $em->getRepository(UpgradeRepository::class)->massInserts(array_keys($datas[0]), $datas);

        $file_content = '<?php' . "\n";
        $file_content .= '$conf[\'dblayer\'] = \'' . $db_params['db_layer'] . "';\n";
        $file_content .= '$conf[\'db_base\'] = \'' . $db_params['db_name'] . "';\n";
        if ($db_params['db_layer'] !== 'sqlite') {
            $file_content .= '$conf[\'db_host\'] = \'' . $db_params['db_host'] . "';\n";
            $file_content .= '$conf[\'db_user\'] = \'' . $db_params['db_user'] . "';\n";
            $file_content .= '$conf[\'db_password\'] = \'' . $db_params['db_password'] . "';\n";
        }
        $file_content .= '$conf[\'db_prefix\'] = \'' . $db_params['db_prefix'] . "';\n\n";

        file_put_contents($this->getApplication()->getKernel()->getDbConfigFile() . '.tmp', $file_content);
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
