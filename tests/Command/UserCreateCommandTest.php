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

namespace App\Tests\Command;

use App\Command\UserCreateCommand;
use App\Enum\UserStatusType;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Console\Test\InteractsWithConsole;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserCreateCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;
    use InteractsWithConsole;

    public function testCanCreateUser(): void
    {
        UserFactory::new(['username' => 'guest'])->withUserInfos(['status' => UserStatusType::GUEST])->create();

        $username = 'nicolas';
        $password = 'my_pass';
        $mail_address = 'nicolas@phyxo.net';
        $status = UserStatusType::NORMAL->value;

        $this->consoleCommand(UserCreateCommand::class)
            ->splitOutputStreams()
            ->addOption('username', $username)
            ->addOption('password', $password)
            ->addOption('mail_address', $mail_address)
            ->addOption('status', $status)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains(sprintf(
                'Successfully created user "%s" with mail address "%s" and status "%s"',
                $username,
                $mail_address,
                $status
            ));
    }

    public function testMissingOption(): void
    {
        $this->consoleCommand(UserCreateCommand::class)
            ->splitOutputStreams()
            ->addOption('status', 'normal')
            ->execute()
            ->assertStatusCode(Command::INVALID)
            ->assertOutputContains('[ERROR] Missing option')
        ;
    }
}
