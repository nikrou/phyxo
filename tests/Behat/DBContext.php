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

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

use App\Entity\User;
use App\Utils\UserManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Phyxo\DBLayer\DBLayer;
use Phyxo\DBLayer\iDBLayer;

class DBContext implements Context
{
    private $userManager, $passwordEncoder;
    private $sqlInitFile, $sqlCleanupFile, $conn;

    public function __construct(UserManager $userManager, UserPasswordEncoderInterface $passwordEncoder, iDBLayer $conn, string $sql_init_file, string $sql_cleanup_file)
    {
        $this->userManager = $userManager;
        $this->passwordEncoder = $passwordEncoder;

        $this->conn = $conn;
        $this->sqlInitFile = $sql_init_file;
        $this->sqlCleanupFile = $sql_cleanup_file;
    }

    /**
     * @Given a user:
     * @Given some users:
     */
    public function givenUsers(TableNode $table): void
    {
        foreach ($table->getHash() as $userRow) {
            $user = new User();
            $user->setUsername($userRow['username']);
            $user->setPassword($this->passwordEncoder->encodePassword($user, $userRow['password']));
            $user->setStatus(User::STATUS_NORMAL);
            $this->userManager->register($user);
        }
    }

    /**
     * @BeforeScenario
     */
    public function prepareDB(BeforeScenarioScope $scope)
    {
        $this->conn->executeSqlFile($this->sqlInitFile, DBLayer::DEFAULT_PREFIX, $this->conn->getPrefix());
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(AfterScenarioScope $scope)
    {
        $this->conn->executeSqlFile($this->sqlCleanupFile, DBLayer::DEFAULT_PREFIX, $this->conn->getPrefix());
    }
}
