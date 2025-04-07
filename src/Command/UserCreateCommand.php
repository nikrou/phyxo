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

use App\Entity\User;
use App\Enum\UserStatusType;
use App\Security\AppUserService;
use Exception;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'phyxo:user:create',
    description: 'Creates user'
)]
class UserCreateCommand extends Command
{
    private string $localEnvFile = '';

    public function __construct(private readonly AppUserService $appUserService, private readonly UserPasswordHasherInterface $passwordHasher, private readonly string $rootProjectDir)
    {
        parent::__construct();
        $this->localEnvFile = sprintf('%s/.env.local', $this->rootProjectDir);
    }

    #[Override]
    public function isEnabled(): bool
    {
        return is_readable($this->localEnvFile);
    }

    protected function configure(): void
    {
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/UserCreateCommand.txt'))
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Password')
            ->addOption('mail_address', null, InputOption::VALUE_OPTIONAL, 'Mail address')
            ->addOption('status', null, InputOption::VALUE_OPTIONAL, 'User status');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption('verbose')) {
            $io->title('Create user');
        }

        if (!$input->getOption('username')) {
            $username = $io->ask(
                'Username',
                null,
                function ($user) {
                    if (empty($user)) {
                        throw new Exception('Username cannot be empty.');
                    }

                    return $user;
                }
            );
            $input->setOption('username', $username);
        } elseif ($input->getOption('verbose')) {
            $io->text(sprintf('<info>Username is:</info> %s', $input->getOption('username')));
        }

        if (is_null($input->getOption('password'))) {
            $input->setOption('password', $io->askHidden('Password for username'));
        } elseif ($input->getOption('verbose')) {
            $io->text(sprintf('<info>Password is:</info> %s', $io->isVerbose() ? $input->getOption('password') : '****'));
        }

        if (is_null($input->getOption('mail_address'))) {
            $input->setOption('mail_address', $io->ask('Mail address'));
        } elseif ($input->getOption('verbose')) {
            $io->text(sprintf('<info>Mail address is:</info> %s', $input->getOption('mail_address')));
        }

        if (is_null($input->getOption('status'))) {
            $status_options = [];
            foreach (UserStatusType::cases() as $status) {
                $status_options[] = $status->value;
            }

            $input->setOption('status', $io->choice('Select user status', $status_options, UserStatusType::NORMAL->value));
        } elseif ($input->getOption('verbose')) {
            $io->text(sprintf('<info>User status type is:</info> %s', $input->getOption('status')));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (is_null($input->getOption('username')) || is_null($input->getOption('status'))) {
            $io->error('Missing option');

            return Command::INVALID;
        }

        $user = new User();
        $user->addRole(User::getRoleFromStatus(UserStatusType::from($input->getOption('status'))));
        $user->setUsername($input->getOption('username'));
        $user->setMailAddress($input->getOption('mail_address'));
        if (!is_null($input->getOption('password'))) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $input->getOption('password')));
        }

        try {
            $user = $this->appUserService->register($user);
            $output->writeln(sprintf(
                'Successfully created user "%s" with mail address "%s" and status "%s"',
                $user->getUsername(),
                $user->getMailAddress(),
                $user->getUserInfos()->getStatus()->value
            ));
        } catch (Exception $exception) {
            $io->error($exception->getMessage());
        }

        return Command::SUCCESS;
    }
}
