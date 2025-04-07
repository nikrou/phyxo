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

use App\Repository\UserRepository;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'phyxo:user:list',
    description: 'Shows list of users'
)]
class UserListCommand extends Command
{
    /** @var array<string, string> */
    private array $Fields = [
        'id' => 'Id',
        'username' => 'Username',
        'mail_address' => 'Mail Address',
        'status' => 'Status',
        'language' => 'Language',
        'theme' => 'Theme',
    ];
    private string $localEnvFile = '';

    public function __construct(private readonly UserRepository $userRepository, private readonly string $rootProjectDir)
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
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/UserListCommand.txt'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = [];
        foreach ($this->userRepository->findAll() as $user) {
            $users[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'mail_address' => $user->getMailAddress(),
                'status' => $user->getUserInfos()->getStatusValue(),
                'language' => $user->getUserInfos()->getLanguage(),
                'theme' => $user->getUserInfos()->getTheme(),
            ];
        }

        $io->table(array_values($this->Fields), $users);

        return Command::SUCCESS;
    }
}
