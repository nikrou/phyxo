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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'phyxo:user:list',
    description: 'Shows list of users'
)]
class UserListCommand extends Command
{
    private array $Fields = [
        'id' => 'Id',
        'username' => 'Username',
        'mail_address' => 'Mail Address',
        'status' => 'Status',
        'language' => 'Language',
        'theme' => 'Theme',
    ];

    public function __construct(private readonly UserRepository $userRepository, private readonly string $databaseYamlFile)
    {
        parent::__construct();
    }

    public function isEnabled(): bool
    {
        return is_readable($this->databaseYamlFile);
    }

    public function configure(): void
    {
        $this->setHelp(file_get_contents(__DIR__ . '/../Resources/help/UserListCommand.txt'));
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = [];
        foreach ($this->userRepository->findAll() as $user) {
            $users[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'mail_address' => $user->getMailAddress(),
                'status' => $user->getUserInfos()->getStatus(),
                'language' => $user->getUserInfos()->getLanguage(),
                'theme' => $user->getUserInfos()->getTheme(),
            ];
        }

        $io->table(array_values($this->Fields), $users);

        return (int) Command::SUCCESS;
    }
}
