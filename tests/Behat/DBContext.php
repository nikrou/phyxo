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

use DateTime;
use Exception;
use App\DataMapper\AlbumMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Entity\Comment;
use App\Entity\Group;
use App\Entity\Image;
use App\Entity\ImageTag;
use App\Entity\Tag;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Utils\UserManager;
use Doctrine\DBAL\Connection;
use Phyxo\Conf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class DBContext implements Context
{
    public function __construct(
        private readonly string $sqlInitFile,
        private readonly string $sqlCleanupFile,
        private readonly Storage $storage,
        private readonly ContainerInterface $driverContainer,
        private readonly UserManager $userManager,
        private readonly AlbumMapper $albumMapper,
        private Conf $conf,
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ImageMapper $imageMapper,
        private readonly TagMapper $tagMapper,
        private readonly UserMapper $userMapper,
        private readonly string $prefix,
        private Connection $connection
    ) {
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->driverContainer;
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
            $user->setPassword($this->getContainer()->get('security.password_hasher')->hashPassword($user, $userRow['password']));
            $user->addRole(User::getRoleFromStatus(!empty($userRow['status']) ? $userRow['status'] : User::STATUS_NORMAL));
            if (!empty($userRow['mail_address'])) {
                $user->setMailAddress($userRow['mail_address']);
            }
            $this->userManager->register($user);

            if (!empty($userRow['activation_key'])) {
                $user->getUserInfos()->setActivationKey($userRow['activation_key']);
            }
            if (!empty($userRow['activation_key_expire'])) {
                $user->getUserInfos()->setActivationKeyExpire(new DateTime($userRow['activation_key_expire']));
            }
            $this->userRepository->updateUser($user);

            $this->storage->set('user_' . $userRow['username'], $user);
        }
    }

    /**
     * @Given a group:
     * @Given some groups:
     */
    public function someGroups(TableNode $table): void
    {
        foreach ($table->getHash() as $groupRow) {
            $group = new Group();
            $group->setName($groupRow['name']);
            if (!empty($groupRow['users'])) {
                foreach (explode(',', (string) $groupRow['users']) as $user_name) {
                    $user = $this->storage->get('user_' . trim($user_name));
                    if ($user === null) {
                        throw new Exception(sprintf('User "%s" not found in database', $user_name));
                    }
                    $group->addUser($user);
                }
            }

            $this->groupRepository->addOrUpdateGroup($group);
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
            $album = $this->albumMapper->createAlbum($albumRow['name'], 0, $parent, [], $albumRow);
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
    public function givenImages(TableNode $table): void
    {
        foreach ($table->getHash() as $image) {
            $image_params = array_filter($image, fn($k) => !in_array($k, ['album', 'tags']), ARRAY_FILTER_USE_KEY);

            $this->addImage($image_params);
            if (!empty($image['album'])) {
                $this->associateImageToAlbum($image['name'], $image['album']);
            }

            if (!empty($image['tags'])) {
                if (preg_match('`\[(.*)]`', (string) $image['tags'], $matches)) {
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
    public function groupCanAccessAlbum(string $group_name, string $album_name): void
    {
        $group = $this->groupRepository->findOneByName($group_name);
        if (is_null($group)) {
            throw new Exception(sprintf('Group with name "%s" do not exists', $group_name));
        }
        $album = $this->albumMapper->getRepository()->findOneByName($album_name);
        $album->addGroupAccess($group);
        $this->albumMapper->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @Given user ":username" can access album ":album_name"
     */
    public function userCanAccessAlbum(string $username, string $album_name): void
    {
        $user = $this->userRepository->findOneByUsername($username);
        if (is_null($user)) {
            throw new Exception(sprintf('User with username "%s" do not exists', $username));
        }
        $album = $this->albumMapper->getRepository()->findOneByName($album_name);
        $album->addUserAccess($user);
        $this->albumMapper->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @Given user :username cannot access album ":album_name"
     */
    public function userCannotAccessAlbum(string $username, string $album_name): void
    {
        $user = $this->userRepository->findOneByUsername($username);
        if (is_null($user)) {
            throw new Exception(sprintf('User with username "%s" do not exists', $username));
        }

        $album = $this->albumMapper->getRepository()->findOneByName($album_name);
        $album->removeUserAccess($user);
        $this->albumMapper->getRepository()->addOrUpdateAlbum($album);
    }

    /**
     * @When config for :param equals to :value
     * @When config for :param of type :type equals to :value
     */
    public function configForParamEqualsTo(string $param, string $value, string $type = 'string'): void
    {
        $conf = $this->conf;
        $conf->addOrUpdateParam($param, $conf->dbToConf($value, $type), $type);
    }

    /**
     * @Given I add tag :tag_name on photo :photo_name by user :user not validated
     */
    public function addTagOnPhoto(string $tag_name, string $photo_name, string $username): void
    {
        if (($image = $this->storage->get('image_' . $photo_name)) === null) {
            throw new Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user = $this->storage->get('user_' . $username)) === null) {
            throw new Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->addTagsToImage([$tag_name], $image->getId(), $user->getId(), $validated = false);
    }

    /**
     * @Given I remove tag :tag_name on photo :photo_name by user :user not validated
     */
    public function removeTagOnPhotoNotValidated(string $tag_name, string $photo_name, string $username): void
    {
        if (($image = $this->storage->get('image_' . $photo_name)) === null) {
            throw new Exception(sprintf('Photo with name "%s" do not exist', $photo_name));
        }

        if (($user = $this->storage->get('user_' . $username)) === null) {
            throw new Exception(sprintf('User with name "%s" do not exist', $username));
        }

        $this->removeTagsFromImage([$tag_name], $image->getId(), $user->getId(), $validated = false);
    }

    /**
     * @Given a comment :comment on :photo_name by :username
     */
    public function givenCommentOnPhotoByUser(string $comment_content, string $photo_name, string $username): void
    {
        $comment = new Comment();
        $comment->setUser($this->storage->get('user_' . $username));
        $comment->setImage($this->storage->get('image_' . $photo_name));
        $comment->setContent($comment_content);
        $comment->setDate(new DateTime());
        $comment->setValidated(true);
        $comment->setAnonymousId(md5('::1'));

        $comment_id = $this->commentRepository->addOrUpdateComment($comment);

        $this->storage->set('comment_' . md5($comment_content), $comment_id);
    }

    /**
     * @BeforeScenario
     */
    public function prepareDB(BeforeScenarioScope $scope): void
    {
        $this->executeSqlFile($this->sqlInitFile, $this->prefix, 'phyxo_');
        $this->cleanUploadAndMediaDirectories();
    }

    /**
     * @AfterScenario
     */
    public function cleanDB(AfterScenarioScope $scope): void
    {
        $this->executeSqlFile($this->sqlCleanupFile, $this->prefix, 'phyxo_');
        $this->cleanUploadAndMediaDirectories();
    }

    protected function cleanUploadAndMediaDirectories(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->getContainer()->getParameter('upload_dir') . '/*');
        $fs->remove($this->getContainer()->getParameter('media_cache_dir') . '/*');
    }

    /**
     * @param array<string> $image_infos
     */
    protected function addImage(array $image_infos): void
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
        $now = new DateTime('now');
        $upload_dir = sprintf('%s/%s', $this->getContainer()->getParameter('upload_dir'), $now->format('Y/m/d'));

        $image_path = sprintf('%s/%s-%s.jpg', $upload_dir, $now->format('YmdHis'), substr((string) $image->getMd5sum(), 0, 8));
        $image->setDateAvailable($now);
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        copy($image->getFile(), $image_path);

        if (!empty($image_infos['author'])) {
            $image->setAuthor($image_infos['author']);
        }

        if (!empty($image_infos['date_creation'])) {
            $image->setDateCreation(new DateTime($image_infos['date_creation']));
        }

        $image->setFile(basename((string) $image->getFile()));
        $image->setPath(substr($image_path, strlen($this->getContainer()->getParameter('root_project_dir')) + 1));

        $this->imageMapper->getRepository()->addOrUpdateImage($image);

        $this->storage->set('image_' . $image_infos['name'], $image);
    }

    protected function associateImageToAlbum(string $image_name, string $album_name): void
    {
        try {
            $album = $this->albumMapper->getRepository()->findOneByName($album_name);
        } catch (Exception) {
            throw new Exception('Album with name ' . $album_name . ' does not exist');
        }

        $image_id = $this->storage->get('image_' . $image_name)->getId();

        $this->albumMapper->associateImagesToAlbums([$image_id], [$album->getId()]);
    }

    protected function addTag(string $tag_name): void
    {
        if (!is_null($this->tagMapper->getRepository()->findOneBy(['name' => $tag_name]))) {
            throw new Exception("Tag already exists");
        } else {
            $tag = new Tag();
            $tag->setName($tag_name);
            $tag->setUrlName($tag_name);
            $tag->setLastModified(new DateTime());
            $this->tagMapper->getRepository()->addOrUpdateTag($tag);

            $this->storage->set('tag_' . $tag_name, $tag->getId());
        }
    }

    /**
     * @param array<string> $tags
     */
    protected function addTagsToImage(array $tags, int $image_id, int $user_id = null, bool $validated = true): void
    {
        $tag_ids = [];

        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        $image = $this->imageMapper->getRepository()->find($image_id);
        if (!is_null($user_id)) {
            $user = $this->userRepository->find($user_id);
        } else {
            $user = $this->userMapper->getDefaultUser();
        }

        $this->tagMapper->toBeValidatedTags($image, $tag_ids, $user, ImageTag::STATUS_TO_ADD, $validated);
    }

    /**
     * @param array<string> $tags
     */
    protected function removeTagsFromImage(array $tags, int $image_id, int $user_id = null, bool $validated = true): void
    {
        $tag_ids = [];

        foreach ($tags as $tag) {
            if (($tag_id = $this->storage->get('tag_' . $tag)) === null) {
                $this->addTag($tag);
                $tag_id = $this->storage->get('tag_' . $tag);
            }
            $tag_ids[] = $tag_id;
        }

        $conf = $this->conf;
        $image = $this->imageMapper->getRepository()->find($image_id);
        if (!is_null($user_id)) {
            $user = $this->userRepository->find($user_id);
        } else {
            $user = $this->userMapper->getDefaultUser();
        }

        // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is true
        if (isset($conf['publish_tags_immediately']) && $conf['publish_tags_immediately'] === false) {
            $this->tagMapper->toBeValidatedTags($image, $tag_ids, $user, ImageTag::STATUS_TO_DELETE, $validated);
        } else {
            $this->tagMapper->dissociateTags($tag_ids, $image_id);
        }
    }

    /**
     * Returns queries from an SQL file.
     * Before executing a query, $replaced is... replaced by $replacing.
     */
    protected function executeSqlFile(string $filepath, string $replaced, string $replacing): void
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

                $this->connection->query($query);
                $query = '';
            }
        }
    }
}
