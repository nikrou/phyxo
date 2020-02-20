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
use App\Repository\UserAccessRepository;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Phyxo\DBLayer\DBLayer;

class DBContext implements Context
{
    private $sqlInitFile, $sqlCleanupFile;

    use KernelDictionary;
    use ContainerAccesser;

    private $storage;

    public function __construct(string $sql_init_file, string $sql_cleanup_file, Storage $storage)
    {
        $this->sqlInitFile = $sql_init_file;
        $this->sqlCleanupFile = $sql_cleanup_file;
        $this->storage = $storage;
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
            $user->setPassword($this->getPasswordEncoder()->encodePassword($user, $userRow['password']));
            $user->setStatus(User::STATUS_NORMAL);
            $user_id = $this->getUserManager()->register($user);
            $this->storage->set('user_' . $userRow['username'], $user_id);
        }
    }

    /**
     * @Given an album:
     * @Given some albums:
     */
    public function givenAlbums(TableNode $table): void
    {
        foreach ($table->getHash() as $albumRow) {
            $album_id = $this->getCategoryMapper()->createAlbum($albumRow['name'], null, $albumRow);
            $this->storage->set('album_' . $albumRow['name'], $album_id);
        }
    }

    /**
     * @Given an image:
     * @Given some images:
     */
    public function givenImages(TableNode $table)
    {
        foreach ($table->getHash() as $image) {
            $image_params = array_filter($image, function($k) {
                return $k != 'album';
            }, ARRAY_FILTER_USE_KEY);

            $this->addImageToAlbum($image_params, $image['album']);
        }
    }

    /**
     * @Given user ":username" can access album ":album_name"
     */
    public function userCanAccessAlbum(string $username, string $album_name)
    {
        $user_id = $this->getUserMapper()->getUserId($username);
        $album = $this->getCategoryMapper()->findAlbumByName($album_name);

        $this->getEntityManager()->getRepository(UserAccessRepository::class)->insertUserAccess(['user_id', 'cat_id'], [['user_id' => $user_id, 'cat_id' => $album['id']]]);
    }

    /**
     * @Given user :username cannot access album ":album_name"
     */
    public function userCannotAccessAlbum(string $username, string $album_name)
    {
        $user_id = $this->getUserMapper()->getUserId($username);
        $album = $this->getCategoryMapper()->findAlbumByName($album_name);

        $this->getEntityManager()->getRepository(UserAccessRepository::class)->deleteByUserIdsAndCatIds([$user_id], [$album['id']]);
    }

    /**
     * @When config for :arg1 equals to :arg2 of type :arg3
     */
    public function configForEqualsToOfType($arg1, $arg2, $arg3)
    {
        throw new PendingException();
    }

    /**
     * @BeforeScenario
     */
    public function prepareDB(BeforeScenarioScope $scope)
    {
        $this->getConnection()->executeSqlFile($this->sqlInitFile, DBLayer::DEFAULT_PREFIX, $this->getConnection()->getPrefix());
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(AfterScenarioScope $scope)
    {
        $this->getConnection()->executeSqlFile($this->sqlCleanupFile, DBLayer::DEFAULT_PREFIX, $this->getConnection()->getPrefix());
    }

    protected function addImageToAlbum(array $image, string $album_name)
    {
        try {
            $album = $this->getCategoryMapper()->findAlbumByName($album_name);
        } catch (\Exception $e) {
            throw new \Exception('Album with name ' . $album_name . ' does not exist');
        }

        $image['date_available'] = (new \DateTime())->format('Y-m-d H:i:s');
        $image_id = $this->getImageMapper()->addImage($image);
        $this->storage->set('image_' . $image['name'], $image_id);

        $this->getCategoryMapper()->associateImagesToCategories([$image_id], [$album['id']]);
    }
}
