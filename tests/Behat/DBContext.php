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

use App\DataMapper\AlbumMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Entity\Group;
use App\Entity\Image;
use App\Entity\ImageTag;
use App\Entity\Tag;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use App\Entity\User;
use App\Utils\UserManager;
use Behat\Behat\Tester\Exception\PendingException;
use Doctrine\Persistence\ManagerRegistry;
use Phyxo\Conf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class DBContext implements Context
{
    private $sqlInitFile, $sqlCleanupFile, $kernel;

    private $storage;

    public function __construct(string $sql_init_file, string $sql_cleanup_file, Storage $storage, KernelInterface $kernel)
    {
        $this->sqlInitFile = $sql_init_file;
        $this->sqlCleanupFile = $sql_cleanup_file;
        $this->storage = $storage;

        $this->kernel = $kernel;
    }

    protected function getContainer():  ContainerInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->kernel->getContainer()->get('test.service_container');
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
            $user->setPassword($this->getContainer()->get('security.password_encoder')->encodePassword($user, $userRow['password']));
            $user->addRole(User::getRoleFromStatus(!empty($userRow['status']) ? $userRow['status'] : User::STATUS_NORMAL));
            if (!empty($userRow['mail_address'])) {
                $user->setMailAddress($userRow['mail_address']);
            }
            $this->getContainer()->get(UserManager::class)->register($user);
            $this->storage->set('user_' . $userRow['username'], $user);
        }
    }

    /**
     * @Given a group:
     * @Given some groups:
     */
    public function someGroups(TableNode $table)
    {
        $groupRepository = $this->getContainer()->get(ManagerRegistry::class)->getRepository(Group::class);

        foreach ($table->getHash() as $groupRow) {
            $group = new Group();
            $group->setName($groupRow['name']);
            if (!empty($groupRow['users'])) {
                foreach (explode(',', $groupRow['users']) as $user_name) {
                    $user = $this->storage->get('user_' . trim($user_name));
                    if ($user === null) {
                        throw new \Exception(sprintf('User "%s" not found in database', $user_name));
                    }
                    $group->addUser($user);
                }
            }

            $groupRepository->addOrUpdateGroup($group);
            $this->storage->set('group_' . $groupRow['name'], $group);
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
            if (isset($albumRow['parent']) && $albumRow['parent'] != '') {
                $parent = $this->storage->get('album_' . $albumRow['parent']);
            }
            $album = $this->getContainer()->get(AlbumMapper::class)->createAlbum($albumRow['name'], $parent, 0, [], $albumRow);
            $this->storage->set('album_' . $albumRow['name'], $album);
        }
    }

    /**
     * Example: And some images:
     * | name    | album     |
     * | photo 1 | album 1   |
     * | photo 2 | album 2   |
     *
     * Example: And some images:
     * | file                     | name    | album   | author  | date_creation       | tags          |
     * | features/media/img_1.jpg | photo 1 | album 1 | author1 | 2019-04-02 11:00:00 | [tag 1,tag 2] |
     * | features/media/img_2.jpg | photo 2 | album 2 | author2 | 2019-05-01 13:00:00 | tag 1         |
     * | features/media/img_3.jpg | photo 3 | album 2 | author3 | 2019-06-11 14:00:00 |               |
     * | features/media/img_4.jpg | photo 4 | album 3 | author1 | 2020-04-01 14:00:00 | [tag 3,tag 2] |
     * | features/media/img_5.jpg | photo 5 | album 2 | author2 | 2020-04-03 14:00:00 | tag 2         |
     *
     * @Given an image:
     * @Given some images:
     */
    public function givenImages(TableNode $table)
    {
        foreach ($table->getHash() as $image) {
            $image_params = array_filter($image, function($k) {
                return !in_array($k, ['album', 'tags']);
            }, ARRAY_FILTER_USE_KEY);

            $this->addImage($image_params);
            if (!empty($image['album'])) {
                $this->associateImageToAlbum($image['name'], $image['album']);
            }

            if (!empty($image['tags'])) {
                if (preg_match('`\[(.*)]`', $image['tags'], $matches)) {
                    $tags = array_map('trim', explode(',', $matches[1]));
                } else {
                    $tags = [$image['tags']];
                }

                $this->addTagsToImage($tags, $this->storage->get('image_' . $image['name'])->getId());
            }
        }
    }

    /**
     * @Given group :group_name can access album :album_name
     */
    public function groupCanAccessAlbum(string $group_name, string $album_name)
    {
        $group = $this->getContainer()->get(ManagerRegistry::class)->getRepository(Group::class)->findOneByName($group_name);
        if (is_null($group)) {
            throw new \Exception(sprintf('Group with name "%s" do not exists', $group_name));
        }
        $album = $this->getContainer()->get(AlbumMapper::class)->getRepository()->findOneByName($album_name);
        $album->addGroupAccess($group);
        $this->getContainer()->get(AlbumMapper::class)->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @Given user ":username" can access album ":album_name"
     */
    public function userCanAccessAlbum(string $username, string $album_name)
    {
        $user = $this->getContainer()->get(ManagerRegistry::class)->getRepository(User::class)->findOneByUsername($username);
        if (is_null($user)) {
            throw new \Exception(sprintf('User with username "%s" do not exists', $username));
        }
        $album = $this->getContainer()->get(AlbumMapper::class)->getRepository()->findOneByName($album_name);
        $album->addUserAccess($user);
        $this->getContainer()->get(AlbumMapper::class)->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @Given user :username cannot access album ":album_name"
     */
    public function userCannotAccessAlbum(string $username, string $album_name)
    {
        $user = $this->getContainer()->get(ManagerRegistry::class)->getRepository(User::class)->findOneByUsername($username);
        if (is_null($user)) {
            throw new \Exception(sprintf('User with username "%s" do not exists', $username));
        }

        $album = $this->getContainer()->get(AlbumMapper::class)->getRepository()->findOneByName($album_name);
        $album->removeUserAccess($user);
        $this->getContainer()->get(AlbumMapper::class)->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @When config for :param equals to :value
     * @When config for :param of type :type equals to :value
     */
    public function configForParamEqualsTo(string $param, string $value, string $type = 'string')
    {
        $conf = $this->getContainer()->get(Conf::class);
        $conf->addOrUpdateParam($param, $conf->dbToConf($value, $type), $type);
    }

    /**
     * @Given I add tag :tag_name on photo :photo_name by user :user not validated
     */
    public function addTagOnPhoto(string $tag_name, string $photo_name, string $username)
    {
        if (($image = $this->storage->get('image_' . $photo_name)) === null) {
            throw new \Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user = $this->storage->get('user_' . $username)) === null) {
            throw new \Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->addTagsToImage([$tag_name], $image->getId(), $user->getId(), $validated = false);
    }

    /**
     * @Given I remove tag :tag_name on photo :photo_name by user :user not validated
     */
    public function removeTagOnPhotoNotValidated(string $tag_name, string $photo_name, string $username)
    {
        if (($image = $this->storage->get('image_' . $photo_name)) === null) {
            throw new \Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user = $this->storage->get('user_' . $username)) === null) {
            throw new \Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->removeTagsFromImage([$tag_name], $image->getId(), $user->getId(), $validated = false);
    }

    /**
     * @Given a comment :comment on :photo_name by :username
     */
    public function givenCommentOnPhotoByUser(string $comment, string $photo_name, string $username)
    {
        $comment_id = $this->getContainer()->get(CommentMapper::class)->createComment(
            $comment,
            $this->storage->get('image_' . $photo_name)->getId(),
            $username,
            $this->storage->get('user_' . $username)->getId()
        );
        $this->storage->set('comment_' . md5($comment), $comment_id);
    }

    /**
     * @BeforeScenario
     */
    public function prepareDB(BeforeScenarioScope $scope)
    {
        $this->executeSqlFile($this->sqlInitFile, $this->getContainer()->getParameter('database_prefix'), 'phyxo_');
        $this->cleanUploadAndMediaDirectories();
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(AfterScenarioScope $scope)
    {
        $this->executeSqlFile($this->sqlCleanupFile, $this->getContainer()->getParameter('database_prefix'), 'phyxo_');
        $this->cleanUploadAndMediaDirectories();
    }

    protected function cleanUploadAndMediaDirectories()
    {
        $fs = new Filesystem();
        $fs->remove($this->getContainer()->getParameter('upload_dir') . '/*');
        $fs->remove($this->getContainer()->getParameter('media_cache_dir') . '/*');
    }

    protected function addImage(array $image_infos)
    {
        $image = new Image();
        $image->setName($image_infos['name']);
        $image->setAddedBy(0);
        if (empty($image_infos['file'])) {
            $image->setFile(sprintf('%s/features/media/sample.jpg', $this->getContainer()->getParameter('root_project_dir')));
        } else {
            $image->setFile($image_infos['file']);
        }
        $image_dimensions = getimagesize($image->getFile());
        $image->setWidth($image_dimensions[0]);
        $image->setHeight($image_dimensions[1]);

        $image->setMd5sum(md5_file($image->getFile()));
        $now = new \DateTime('now');
        $upload_dir = sprintf('%s/%s', $this->getContainer()->getParameter('upload_dir'), $now->format('Y/m/d'));

        $image_path = sprintf('%s/%s-%s.jpg', $upload_dir, $now->format('YmdHis'), substr($image->getMd5sum(), 0, 8));
        $image->setDateAvailable($now);
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        copy($image->getFile(), $image_path);

        if (!empty($image_infos['author'])) {
            $image->setAuthor($image_infos['author']);
        }

        if (!empty($image_infos['date_creation'])) {
            $image->setDateCreation(new \DateTime($image_infos['date_creation']));
        }

        $image->setFile(basename($image->getFile()));
        $image->setPath(substr($image_path, strlen($this->getContainer()->getParameter('root_project_dir')) + 1));

        $this->getContainer()->get(ImageMapper::class)->getRepository()->addOrUpdateImage($image);

        $this->storage->set('image_' . $image_infos['name'], $image);
    }

    protected function associateImageToAlbum(string $image_name, string $album_name)
    {
        try {
            $album = $this->getContainer()->get(AlbumMapper::class)->getRepository()->findOneByName($album_name);
        } catch (\Exception $e) {
            throw new \Exception('Album with name ' . $album_name . ' does not exist');
        }

        $image_id = $this->storage->get('image_' . $image_name)->getId();

        $this->getContainer()->get(AlbumMapper::class)->associateImagesToAlbums([$image_id], [$album->getId()]);
    }

    protected function addTag(string $tag_name)
    {
        if (!is_null($this->getContainer()->get(TagMapper::class)->getRepository()->findOneBy(['name' => $tag_name]))) {
            throw new \Exception("Tag already exists");
        } else {
            $tag = new Tag();
            $tag->setName($tag_name);
            $tag->setUrlName($tag_name);
            $tag->setLastModified(new \DateTime());
            $this->getContainer()->get(TagMapper::class)->getRepository()->addOrUpdateTag($tag);

            $this->storage->set('tag_' . $tag_name, $tag->getId());
        }
    }

    protected function addTagsToImage(array $tags, int $image_id, int $user_id = null, bool $validated = true)
    {
        $tag_ids = [];

        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        $image = $this->getContainer()->get(ImageMapper::class)->getRepository()->find($image_id);
        if (!is_null($user_id)) {
            $user = $this->getContainer()->get(ManagerRegistry::class)->getRepository(User::class)->find($user_id);
        } else {
            $user = $this->getContainer()->get(UserMapper::class)->getDefaultUser();
        }

        $this->getContainer()->get(TagMapper::class)->toBeValidatedTags($image, $tag_ids, $user, ImageTag::STATUS_TO_ADD, $validated);
    }

    protected function removeTagsFromImage(array $tags, int $image_id, int $user_id = null, bool $validated = true)
    {
        $tag_ids = [];

        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        $conf = $this->getContainer()->get(Conf::class);
        $image = $this->getContainer()->get(ImageMapper::class)->getRepository()->find($image_id);
        if (!is_null($user_id)) {
            $user = $this->getContainer()->get(ManagerRegistry::class)->getRepository(User::class)->find($user_id);
        } else {
            $user = $this->getContainer()->get(UserMapper::class)->getDefaultUser();
        }

        // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is true
        if (isset($conf['publish_tags_immediately']) && $conf['publish_tags_immediately'] === false) {
            $this->getContainer()->get(TagMapper::class)->toBeValidatedTags($image, $tag_ids, $user, ImageTag::STATUS_TO_DELETE, $validated);
        } else {
            $this->getContainer()->get(TagMapper::class)->dissociateTags($tag_ids, $image_id);
        }
    }

    /**
     * Returns queries from an SQL file.
     * Before executing a query, $replaced is... replaced by $replacing.
     */
    protected function executeSqlFile(string $filepath, string $replaced, string $replacing)
    {
        $sql_lines = file($filepath);
        $query = '';
        foreach ($sql_lines as $sql_line) {
            $sql_line = trim($sql_line);
            if (preg_match('/(^--|^$)/', $sql_line)) {
                continue;
            }
            $query .= ' ' . $sql_line;
            // if we reached the end of query, we execute it and reinitialize the variable "query"
            if (preg_match('/;$/', $sql_line)) {
                $query = trim($query);
                $query = str_replace($replaced, $replacing, $query);

                $this->getContainer()->get(ManagerRegistry::class)->getConnection()->query($query);
                $query = '';
            }
        }
    }
}
