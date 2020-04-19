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
            $user->setStatus(!empty($userRow['status']) ? $userRow['status'] : User::STATUS_NORMAL);
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
            $parent = null;
            if ($albumRow['parent'] !== null) {
                $parent = $this->storage->get('album_' . $albumRow['parent']);
            }
            $album_id = $this->getCategoryMapper()->createAlbum($albumRow['name'], $parent, $albumRow);
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
                return !in_array($k, ['album', 'tags']);
            }, ARRAY_FILTER_USE_KEY);

            $this->addImageToAlbum($image_params, $image['album']);
            if (!empty($image['tags'])) {
                if (preg_match('`\[(.*)]`', $image['tags'], $matches)) {
                    $tags = array_map('trim', explode(',', $matches[1]));
                } else {
                    $tags = [$image['tags']];
                }

                $this->addTagsToImage($tags, $this->storage->get('image_' . $image['name']));
            }
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
     * @When config for :param equals to :value
     */
    public function configForParamEqualsTo(string $param, string $value)
    {
        $this->getConf()->addOrUpdateParam($param, $value);
    }

    /**
     * @Given I add tag :tag_name on photo :photo_name by user :user not validated
     */
    public function addTagOnPhoto(string $tag_name, string $photo_name, string $username)
    {
        if (($image_id = $this->storage->get('image_' . $photo_name)) === null) {
            throw new \Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user_id = $this->storage->get('user_' . $username)) === null) {
            throw new \Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->addTagsToImage([$tag_name], $image_id, $user_id, $validated = false);
    }

    /**
     * @Given I remove tag :tag_name on photo :photo_name by user :user not validated
     */
    public function removeTagOnPhotoNotValidated(string $tag_name, string $photo_name, string $username)
    {
        if (($image_id = $this->storage->get('image_' . $photo_name)) === null) {
            throw new \Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user_id = $this->storage->get('user_' . $username)) === null) {
            throw new \Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->removeTagsFromImage([$tag_name], $image_id, $user_id, $validated = false);
    }

    /**
     * @Given a comment :comment on :photo_name by :username
     */
    public function givenCommentOnPhotoByUser(string $comment, string $photo_name, string $username)
    {
        $comment_id = $this->getCommentMapper()->createComment($comment, $this->storage->get('image_' . $photo_name), $username, $this->storage->get('user_' . $username));
        $this->storage->set('comment_' . md5($comment), $comment_id);
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

    protected function addTag(string $tag_name)
    {
        $tag = $this->getTagMapper()->createTag($tag_name);
        if (empty($tag['id'])) {
            throw new \Exception($tag['error']);
        } else {
            $this->storage->set('tag_' . $tag_name, $tag['id']);
        }
    }

    protected function addTagsToImage(array $tags, int $image_id, int $user_id = null, bool $validated = true)
    {
        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        $this->getTagMapper()->toBeValidatedTags($tag_ids, $image_id, ['validated' => $validated, 'user_id' => $user_id]);
    }

    protected function removeTagsFromImage(array $tags, int $image_id, int $user_id = null, bool $validated = true)
    {
        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is 1
        if (isset($this->getConf()['publish_tags_immediately']) && $this->getConf()['publish_tags_immediately'] == 0) {
            $this->getTagMapper()->toBeValidatedTags($tag_ids, $image_id, ['status' => 0, 'validated' => $validated, 'user_id' => $user_id]);
        } else {
            $this->getTagMapper()->dissociateTags($tag_ids, $image_id);
        }
    }
}
