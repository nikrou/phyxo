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

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use App\Utils\UserManager;
use App\Entity\User;
use App\Enum\UserStatusType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'phyxo:user:create',
    description: 'Creates user'
)]
class UserCreateCommand extends Command
{
    /** @var array<string, string> */
    private array $params = ['username' => '', 'password' => '', 'mail_address' => ''];
    private string $localEnvFile = '';

    public function __construct(private readonly UserManager $userManager, private readonly UserPasswordHasherInterface $passwordHasher, private readonly string $rootProjectDir)
    {
        parent::__construct();
        $this->localEnvFile = sprintf('%s/.env.local', $this->rootProjectDir);
    }

    public function isEnabled(): bool
    {
        return is_readable($this->localEnvFile);
    }

    public function configure(): void
    {
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/UserCreateCommand.txt'))
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password')
            ->addOption('mail_address', null, InputOption::VALUE_OPTIONAL, 'Mail address')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'User status');
    }

    public function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create user');

        if (($this->params['username'] = $input->getOption('username')) === null) {
            $this->params['username'] = $io->ask('Username', null, function ($user) {
                if (empty($user)) {
                    throw new Exception('Username cannot be empty.');
                }

                return $user;
            });
            $input->setOption('username', $this->params['username']);
        } else {
            $io->text(sprintf('<info>Username is:</info> %s', $this->params['username']));
        }

        if (($this->params['password'] = $input->getOption('password')) === null) {
            $this->params['password'] = $io->askHidden('Password for username');
            $input->setOption('password', $this->params['password']);
        } else {
            $io->text(sprintf('<info>Password is:</info> %s', $io->isVerbose() ? $this->params['password'] : '****'));
        }

        if (($this->params['mail_address'] = $input->getOption('mail_address')) === null) {
            $this->params['mail_address'] = $io->ask('Mail address');
            $input->setOption('mail_address', $this->params['mail_address']);
        } else {
            $io->text(sprintf('<info>Mail address is:</info> %s', $this->params['mail_address']));
        }

        if (($this->params['status'] = $input->getOption('status')) === null) {
            $status_options = [];
            foreach (UserStatusType::cases() as $status) {
                $status_options[] = $status->value;
            }

            $this->params['status'] = $io->choice('Select user status', $status_options, UserStatusType::NORMAL->value);
            $input->setOption('status', $this->params['status']);
        } else {
            $io->text(sprintf('<info>User status type is:</info> %s', $this->params['status']));
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = new User();
        $user->addRole(User::getRoleFromStatus(UserStatusType::from($this->params['status'])));
        $user->setUsername($this->params['username']);
        $user->setMailAddress($this->params['mail_address']);
        if ($this->params['password']) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $this->params['password']));
        }

        try {
            $this->userManager->register($user);
        } catch (Exception $e) {
            $io->error($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
