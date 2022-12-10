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

use App\Install\PhyxoInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;

#[\Symfony\Component\Console\Attribute\AsCommand('phyxo:install', 'Install Phyxo')]
class InstallCommand extends Command
{
    private array $db_params = ['db_layer' => '', 'db_host' => '', 'db_name' => '', 'db_user' => '', 'db_password' => '', 'db_prefix' => ''];
    private $databaseYamlFile, $rootProjectDir, $phyxoInstaller;
    private string $default_prefix = 'phyxo_';

    public function __construct(PhyxoInstaller $phyxoInstaller, string $databaseYamlFile, string $rootProjectDir)
    {
        parent::__construct();

        $this->phyxoInstaller = $phyxoInstaller;
        $this->databaseYamlFile = $databaseYamlFile;
        $this->rootProjectDir = $rootProjectDir;
    }

    public function isEnabled(): bool
    {
        return !is_readable($this->databaseYamlFile);
    }

    public function configure()
    {
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/InstallCommand.txt'))

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
        $dbengines = $this->phyxoInstaller->availableEngines();
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!empty($_SERVER['DATABASE_URL'])) {
            $this->db_params['dsn'] = $_SERVER['DATABASE_URL'];
            $this->db_params['db_prefix'] = $input->getOption('db_prefix') ?: $this->default_prefix;
        } else {
            if (!$io->askQuestion(new ConfirmationQuestion("Install Phyxo using these settings?", true))) {
                return (int) Command::SUCCESS;
            }
        }

        try {
            $this->phyxoInstaller->installDatabase($this->db_params);

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

            $command->run(new ArrayInput($arguments), $output);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return (int) Command::FAILURE;
        }

        $this->writeInfo($io, "What's next?");
        $io->listing([
            'Create a webmaster, using phyxo:user:create command',
            'Create a guest user, using phyxo:user:create command',
            'Go to http://your.hostname/path/to/phyxo and start using your application'
        ]);

        return (int) Command::SUCCESS;
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
