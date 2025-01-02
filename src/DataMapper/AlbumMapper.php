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

namespace App\DataMapper;

use App\Entity\Image;
use Exception;
use DateTime;
use App\Entity\Album;
use App\Entity\ImageAlbum;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;

class AlbumMapper
{
    /**
     *  @var array<int, Album> $cache
     */
    private array $cache;
    private bool $albums_retrieved = false;

    public function __construct(
        private Conf $conf,
        private readonly AlbumRepository $albumRepository,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        private readonly UserCacheAlbumRepository $userCacheAlbumRepository,
        private readonly ImageAlbumRepository $imageAlbumRepository,
        private readonly ImageRepository $imageRepository
    ) {
    }

    public function getRepository(): AlbumRepository
    {
        return $this->albumRepository;
    }

    /**
     * Returns template vars for main albums menu.
     * @param array{id?: int, id_uppercat?: int} $selected_album
     *
     * @return array<string, mixed>
     */
    public function getRecursiveAlbumsMenu(User $user, array $selected_album = []): array
    {
        $flat_albums = $this->getAlbumsMenu($user, $selected_album);

        $albums = [];
        foreach ($flat_albums as $album) {
            if ($album['uppercats'] == $album['id']) {
                $albums[$album['id']] = $album;
            } else {
                $this->insertAlbumInTree($albums, $album, $album['uppercats']);
            }
        }

        return $albums;
    }

    /**
     * @param array<string, mixed> $categories
     * @param array<string, mixed> $category
     */
    protected function insertAlbumInTree(array &$categories, array $category, string $uppercats): void
    {
        if ($category['id'] != $uppercats) {
            $cats = explode(',', $uppercats);
            $cat = $cats[0];
            $new_uppercats = array_slice($cats, 1);
            if (count($new_uppercats) === 1) {
                $categories[$cat]['children'][$category['id']] = $category;
            } else {
                $this->insertAlbumInTree($categories[$cat]['children'], $category, implode(',', $new_uppercats));
            }
        }
    }

    /**
     * Returns template vars for main albums menu.
     * @param array{id?: int, id_uppercat?: int} $selected_album
     *
     * @return array<string, mixed>
     */
    protected function getAlbumsMenu(User $user, array $selected_album = []): array
    {
        $albums = [];
        foreach ($this->getRepository()->getAlbumsForMenu($user->getId(), $user->getUserInfos()->getForbiddenAlbums()) as $album) {
            $userCacheAlbum = $album->getUserCacheAlbum();
            if (!$userCacheAlbum) {
                continue;
            }
            $title = $this->getDisplayImagesCount(
                $userCacheAlbum->getNbImages() ?? 0,
                $userCacheAlbum->getCountImages() ?? 0,
                $userCacheAlbum->getCountAlbums() ?? 0,
                false,
                ' / '
            );

            $album_infos = array_merge(
                $album->toArray(),
                [
                    'NAME' => $album->getName(),
                    'TITLE' => $title,
                    'URL' => $this->router->generate('album', ['album_id' => $album->getId()]),
                    'LEVEL' => substr_count((string) $album->getGlobalRank(), '.') + 1,
                    'SELECTED' => isset($selected_album['id']) && $selected_album['id'] === $album->getId(),
                    'IS_UPPERCAT' => isset($selected_album['id_uppercat']) && $selected_album['id_uppercat'] === $album->getId(),
                    'count_images' => $userCacheAlbum->getCountImages(),
                    'icon_ts' => ''
                ]
            );

            // if ($this->conf['index_new_icon'] && !empty($row['max_date_last'])) { // @FIX : cf BUGS
            //     $row['icon_ts'] = $this->em->getRepository(BaseRepository::class)->getIcon($row['max_date_last'], $user, $child_date_last);
            // }
            $albums[$album->getId()] = $album_infos;
        }
        uasort($albums, Utils::globalRankCompare(...));

        return $albums;
    }

    /**
     * Get computed array of albums, that means cache data of all albums
     * available for the current user (count_categories, count_images, etc.).
     *
     * @param array<mixed> $forbidden_categories
     *
     * @return array<int, array<string, int|string|null>>
     */
    public function getComputedAlbums(int $level, array $forbidden_categories = []): array
    {
        $albums = [];
        $last_photo_date = null;
        foreach ($this->getRepository()->getComputedAlbums($level, $forbidden_categories) as $album) {
            $album['nb_categories'] = 0;
            $album['count_categories'] = 0;
            $album['count_images'] = $album['nb_images'];
            $album['max_date_last'] = $album['date_last'];

            if ($album['date_last'] > $last_photo_date) {
                $last_photo_date = $album['date_last'];
            }

            $albums[$album['album_id']] = $album;
        }

        foreach ($albums as $album) {
            if (!isset($album['id_uppercat']) || !isset($albums[$album['id_uppercat']])) {
                continue;
            }

            $parent = &$albums[$album['id_uppercat']];
            $parent['nb_categories']++;

            do {
                $parent['count_images'] += $album['nb_images'];
                $parent['count_categories']++;

                if ((empty($parent['max_date_last'])) || ($parent['max_date_last'] < $album['date_last'])) {
                    $parent['max_date_last'] = $album['date_last'];
                }

                if (!isset($parent['id_uppercat']) || !isset($albums[$parent['id_uppercat']])) {
                    break;
                }
                $parent = &$albums[$parent['id_uppercat']];
            } while (true);
            unset($parent);
        }

        return $albums;
    }

    /**
     * Removes an album from computed array of albums and updates counters.
     *
     * @param array<array<string, int|string|null>> $albums
     * @param array<string, mixed> $album
     *
     * @return array<string, mixed>
     */
    public function removeComputedAlbum(array $albums, array $album): array
    {
        if (isset($albums[$album['id_uppercat']])) {
            $parent = $albums[$album['id_uppercat']];
            $parent['nb_categories']--;

            do {
                $parent['count_images'] -= $album['nb_images'];
                $parent['count_categories'] -= 1 + $album['count_categories'];

                if (!isset($albums[$parent['id_uppercat']])) {
                    break;
                }
                $parent = $albums[$parent['id_uppercat']];
            } while (true);
        }

        unset($albums[$album['album_id']]);

        return $albums;
    }

    /**
     * Generates breadcrumb from albums list.
     *
     * @param array<mixed> $album_informations
     */
    public function getAlbumDisplayName(array $album_informations, ?string $url = null): string
    {
        $output = '';
        $is_first = true;

        foreach ($album_informations as $album) {
            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $this->conf['level_separator'];
            }

            if (is_null($url)) {
                $output .= $album['name'];
            } elseif ($url === '') {
                $output .= '<a href="' . $this->router->generate('album', ['album_id' => $album['id']]) . '">';
                $output .= $album['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $album['id'] . '">';
                $output .= $album['name'] . '</a>';
            }
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<array<string, string|null>>
     */
    public function getAlbumsDisplayName(string $uppercats, string $route_name, array $params = []): array
    {
        $names = [];

        foreach (explode(',', $uppercats) as $album_id) {
            $names[] = [
                'name' => $this->getCacheAlbums()[(int) $album_id]->getName(),
                'url' => $this->router->generate($route_name, array_merge($params, ['album_id' => $this->getCacheAlbums()[(int) $album_id]->getId()]))
            ];
        }

        return $names;
    }

    /**
     * @return array<array<string, string|null>>
     */
    public function getBreadcrumb(Album $album): array
    {
        $breadcumb = [];

        $upper_names = [];
        $upper_ids = explode(',', (string) $album->getUppercats());
        if (count($upper_ids) === 1) { // no need to make a query for level 1
            $upper_names = [
                [
                    'id' => $album->getId(),
                    'name' => $album->getName(),
                ]
            ];
        } else {
            foreach ($upper_ids as $album_id) {
                $upper_names[] = [
                    'id' => $album_id,
                    'name' => $this->getCacheAlbums()[(int) $album_id]->getName(),
                ];
            }
        }

        foreach ($upper_names as $album) {
            $breadcumb[] = [
                'url' => $this->router->generate('album', ['album_id' => $album['id']]),
                'label' => $album['name']
            ];
        }

        return $breadcumb;
    }

    /**
     * Generates breadcrumb from albums list using a cache.
     * @see getAlbumDisplayName()
     */
    public function getAlbumsDisplayNameCache(string $uppercats, string $url = '', bool $single_link = false, string $link_class = ''): string
    {
        $all_albums = explode(',', $uppercats);
        $output = '';
        if ($single_link) {
            $single_url = $this->router->generate('album', ['album_id' => $all_albums[count($all_albums) - 1]]);
            $output .= '<a href="' . $single_url . '"';
            if ($link_class !== '' && $link_class !== '0') {
                $output .= ' class="' . $link_class . '"';
            }
            $output .= '>';
        }

        // @TODO: refactoring with getAlbumDisplayName
        $is_first = true;
        foreach ($all_albums as $album_id) {
            $album = $this->getCacheAlbums()[(int) $album_id];

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= ' / ';
            }

            if ($url === '' || $single_link) {
                $output .= $album->getName();
            } else {
                $output .= '<a href="' . $this->router->generate('album', ['album_id' => $album->getId()]) . '">' . $album->getName() . '</a>';
            }
        }

        if ($single_link && isset($single_url)) {
            $output .= '</a>';
        }

        return $output;
    }

    /**
     * @param array<mixed> $albums
     * @param array<string, mixed> $selecteds
     *
     * @return array<string, mixed>
     */
    public function displaySelectAlbums(array $albums, array $selecteds, string $blockname, bool $fullname = true): array
    {
        $tpl_cats = [];
        foreach ($albums as $album) {
            if ($fullname) {
                $option = strip_tags($this->getAlbumsDisplayNameCache($album->getUppercats()));
            } else {
                $option = str_repeat('&nbsp;', (3 * substr_count((string) $album->getGlobalRank(), '.')));
                $option .= '- ';
                $option .= strip_tags((string) $album->getName());
            }
            $tpl_cats[$album->getId()] = $option;
        }

        return [
            $blockname => $tpl_cats,
            $blockname . '_selected' => $selecteds
        ];
    }

    /**
     * Same as displaySelectAlbums but albums are ordered by rank
     * @see displaySelectAlbums()
     *
     * @param array<mixed> $albums
     * @param array<string, mixed> $selecteds
     *
     * @return array<mixed>
     */
    public function displaySelectAlbumsWrapper(array $albums, array $selecteds, string $blockname, bool $fullname = true): array
    {
        usort($albums, self::globalRankCompare(...));

        return $this->displaySelectAlbums($albums, $selecteds, $blockname, $fullname);
    }

    /**
     * Change the parent album of the given albums.
     * @param int[] $ids
     */
    public function moveAlbums(array $ids, ?int $new_parent = null): void
    {
        if ($ids === []) {
            return;
        }

        $albums = [];
        foreach ($ids as $id) {
            $albums[$id] = [
                'parent' => $this->getCacheAlbums()[$id]->getParent(),
                'status' => $this->getCacheAlbums()[$id]->getStatus(),
                'uppercats' => $this->getCacheAlbums()[$id]->getUppercats(),
            ];
        }

        // is the movement possible? The movement is impossible if you try to move an album in a sub-album or itself
        if ($new_parent !== null) {
            $new_parent_uppercats = $this->getCacheAlbums()[$new_parent]->getUppercats();

            foreach ($albums as $album) {
                // technically, you can't move a category with uppercats 12,125,13,14
                // into a new parent category with uppercats 12,125,13,14,24
                if (preg_match('/^' . $album['uppercats'] . '(,|$)/', (string) $new_parent_uppercats)) {
                    throw new Exception($this->translator->trans('You cannot move an album in its own sub album'));
                }
            }
        }

        foreach ($ids as $id) {
            $parent_album = null;
            if ($new_parent !== null) {
                $parent_album = $this->getCacheAlbums()[$new_parent];
            }
            $this->getCacheAlbums()[$id]->setParent($parent_album);
        }

        $this->updateUppercats();
        $this->updateGlobalRank();

        // status and related permissions management
        if ($new_parent === null) {
            $parent_status = Album::STATUS_PUBLIC;
        } else {
            $parent_status = $this->getCacheAlbums()[$new_parent]->getStatus();
        }

        if ($parent_status === Album::STATUS_PRIVATE) {
            $this->albumRepository->updateAlbums(['status' => Album::STATUS_PRIVATE], $ids);
        }
    }

    /**
     * Updates albums uppercats field based on albums id + albums id_uppercat
     */
    public function updateUppercats(): void
    {
        foreach ($this->getCacheAlbums() as $album) {
            $upper_list = [];

            $uppercat = $album;
            while ($uppercat !== null) {
                $upper_list[] = $uppercat->getId();
                $uppercat = $this->getCacheAlbums()[$uppercat->getId()]->getParent();
            }

            $new_uppercats = implode(',', array_reverse($upper_list));
            if ($new_uppercats !== $album->getUppercats()) {
                $album->setUppercats($new_uppercats);
                $this->albumRepository->addOrUpdateAlbum($album);
            }
        }
    }

    /**
     * Change the **status** property on a set of albums : private or public.
     * @param int[] $album_ids
     */
    public function setAlbumsStatus(array $album_ids, string $status): void
    {
        if (!in_array($status, [Album::STATUS_PUBLIC, Album::STATUS_PRIVATE])) {
            throw new Exception("AlbumMapper::setAlbumsStatus invalid param $status");
        }

        // make public an album => all its parent albums become public
        if ($status === Album::STATUS_PUBLIC) {
            $uppercats = $this->getUppercatIds($album_ids);
            $this->albumRepository->updateAlbums(['status' => Album::STATUS_PUBLIC], $uppercats);
        } else { // make an album private => all its child albums become private
            $subalbums = $this->albumRepository->getSubcatIds($album_ids);
            $this->albumRepository->updateAlbums(['status' => Album::STATUS_PRIVATE], $subalbums);

            // @TODO: add unit tests for that
            // We have to keep permissions consistant: a sub-album can't be
            // permitted to a user or group if its parent album is not permitted to
            // the same user or group. Let's remove all permissions on sub-albums if
            // it is not consistant. Let's take the following example:
            //
            // A1        permitted to U1,G1
            // A1/A2     permitted to U1,U2,G1,G2
            // A1/A2/A3  permitted to U3,G1
            // A1/A2/A4  permitted to U2
            // A1/A5     permitted to U4
            // A6        permitted to U4
            // A6/A7     permitted to G1
            //
            // (we consider that it can be possible to start with inconsistant
            // permission, given that public albums can have hidden permissions,
            // revealed once the album returns to private status)
            //
            // The admin selects A2,A3,A4,A5,A6,A7 to become private (all but A1,
            // which is private, which can be true if we're moving A2 into A1). The
            // result must be:
            //
            // A2 permission removed to U2,G2
            // A3 permission removed to U3
            // A4 permission removed to U2
            // A5 permission removed to U2
            // A6 permission removed to U4
            // A7 no permission removed
            //
            // 1) we must extract "top albums": A2, A5 and A6
            // 2) for each top album, decide which album is the reference for permissions
            // 3) remove all inconsistant permissions from sub-albums of each top-album

            // step 1, search top albums
            $top_albums = [];
            $parent_ids = [];
            $all_albums = [];
            foreach ($this->albumRepository->findBy(['id' => $album_ids]) as $album) {
                $all_albums[] = $album;
            }
            usort($all_albums, self::globalRankCompare(...));

            foreach ($all_albums as $album) {
                $is_top = true;

                if ($album->getParent()) {
                    foreach (explode(',', (string) $album->getUppercats()) as $id_uppercat) {
                        if (isset($top_albums[$id_uppercat])) {
                            $is_top = false;
                            break;
                        }
                    }
                }

                if ($is_top) {
                    $top_albums[$album->getId()] = $album;

                    if ($album->getParent()) {
                        $parent_ids[] = $album->getParent()->getId();
                    }
                }
            }

            // step 2, search the reference album for permissions
            //
            // to find the reference of each top album, we will need the parent albums
            /** @var Album[] $parent_albums */
            $parent_albums = [];

            if ($parent_ids !== []) {
                foreach ($this->albumRepository->findBy(['id' => $parent_ids]) as $album) {
                    $parent_albums[] = $album->getId();
                }
            }

            foreach ($top_albums as $top_album) {
                // what is the "reference" for list of permissions?
                // The parent album if it is private, else the album itself
                if ($top_album->getParent() && isset($parent_albums[$top_album->getParent()->getId()]) && $parent_albums[$top_album->getParent()->getId()]->getStatus() === Album::STATUS_PRIVATE) {
                    $ref_album = $top_album->getParent();
                } else {
                    $ref_album = $top_album;
                }

                // step 3, remove the inconsistant permissions from sub-albums
                foreach ($this->getRepository()->getSubAlbums([$top_album->getId()]) as $subalbum) {
                    $subalbum->clearAllGroupAccess();
                    $subalbum->clearAllUserAccess();

                    if (count($ref_album->getGroupAccess()) > 0) {
                        foreach ($ref_album->getGroupAccess() as $group) {
                            $subalbum->addGroupAccess($group);
                        }
                    }
                    if (count($ref_album->getUserAccess()) > 0) {
                        foreach ($ref_album->getUserAccess() as $user) {
                            $subalbum->addUserAccess($user);
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns all uppercats Album ids of the given Album ids.
     * @param int[] $ids
     *
     * @return array<int>
     */
    public function getUppercatIds(array $ids): array
    {
        if (count($ids) < 1) {
            return [];
        }

        $uppercats = [];
        foreach ($ids as $id) {
            $uppercats = array_merge($uppercats, array_map(fn ($s) => intval($s), explode(',', (string) $this->getCacheAlbums()[$id]->getUppercats())));
        }

        return array_unique($uppercats);
    }

    /**
     * Grant access to a list of categories for a list of users.
     * @param int[] $album_ids
     * @param int[] $user_ids
     */
    public function addPermissionOnAlbum(array $album_ids, array $user_ids, bool $apply_on_sub = false): void
    {
        // check for emptiness
        if ($album_ids === [] || $user_ids === []) {
            return;
        }

        // make sure albums are private and select uppercats or subcats
        $cat_ids = $this->getUppercatIds($album_ids);
        if ($apply_on_sub) {
            $cat_ids = array_merge($cat_ids, $this->albumRepository->getSubcatIds($album_ids));
        }

        if ($cat_ids !== []) {
            $users = $this->userRepository->findBy(['id' => $user_ids]);

            foreach ($this->albumRepository->findByIdsAndStatus($cat_ids, Album::STATUS_PRIVATE) as $album) {
                foreach ($users as $user) {
                    $album->addUserAccess($user);
                }
            }
        }
    }

    /**
     * Create an album.
     *
     * @param int[] $admin_ids
     * @param array{commentable?: bool, visible?: bool, status?: string, comment?: string, inherit?: bool} $options
     */
    public function createAlbum(string $name, int $user_id, ?Album $parent = null, array $admin_ids = [], array $options = []): Album
    {
        $album = new Album();
        $album->setName($name);
        if (isset($options['commentable'])) {
            $album->setCommentable($options['commentable']);
        } else {
            $album->setCommentable($this->conf['newcat_default_commentable']);
        }

        if (isset($options['visible'])) {
            $album->setVisible($options['visible']);
        } else {
            $album->setVisible($this->conf['newcat_default_visible']);
        }

        if (isset($options['status'])) {
            $album->setStatus($options['status']);
        } else {
            $album->setStatus($this->conf['newcat_default_status']);
        }

        if (isset($options['comment'])) {
            $album->setComment($this->conf['allow_html_descriptions'] ? $options['comment'] : strip_tags($options['comment']));
        }

        if (!is_null($parent)) {
            $album->setParent($parent);
            $album->setGlobalRank($parent->getGlobalRank() . '.0');

            // at creation, must a category be visible or not ?
            // Warning : if the parent category is invisible, the category is automatically create invisible. (invisible = locked)
            if (!$parent->isVisible()) {
                $album->setVisible(false);
            }
            // at creation, must a category be public or private ?
            // Warning : if the parent category is private, the category is automatically create private.
            if ($parent->getStatus() === Album::STATUS_PRIVATE) {
                $album->setStatus(Album::STATUS_PRIVATE);
            }
        } else {
            $album->setGlobalRank('');
        }

        $album->setUppercats('');
        $album->setLastModified(new DateTime());

        $album_id = $this->albumRepository->addOrUpdateAlbum($album);
        if (!is_null($parent)) {
            $album->setUppercats($parent->getUppercats() . ',' . $album_id);
        } else {
            $album->setUppercats((string) $album_id);
            $album->setGlobalRank((string) $album_id);
        }
        $this->albumRepository->addOrUpdateAlbum($album);

        if ($album->getStatus() === Album::STATUS_PRIVATE) {
            if ($album->getParent() && (!empty($options['inherit']) || $this->conf['inheritance_by_default'])) {
                $parent = $this->albumRepository->find($album->getParent()->getId());
                if (count($parent->getGroupAccess()) > 0) {
                    foreach ($parent->getGroupAccess() as $group) {
                        $album->addGroupAccess($group);
                    }
                }

                if (count($parent->getUserAccess()) > 0) {
                    foreach ($parent->getUserAccess() as $user) {
                        $album->addUserAccess($user);
                    }
                }
            } else {
                foreach ($this->userRepository->findBy(['id' => array_merge($admin_ids, [$user_id])]) as $user) {
                    $album->addUserAccess($user);
                }
            }
        }
        $this->updateGlobalRank();

        return $album;
    }

    /**
     * Callback used for sorting by global_rank
     */
    public static function globalRankCompare(Album $a, Album $b): int
    {
        return strnatcasecmp((string) $a->getGlobalRank(), (string) $b->getGlobalRank());
    }

    /**
     * @return array<int, Album>
     */
    protected function getCacheAlbums()
    {
        if (!$this->albums_retrieved) {
            $this->albums_retrieved = true;

            foreach ($this->albumRepository->findAll() as $album) {
                $this->cache[$album->getId()] = $album;
            }
        }

        return $this->cache;
    }

    /**
     * Change the **visible** property on a set of albums.
     *
     * @param int[] $ids
     */
    public function setAlbumsVisibility(array $ids, bool $visible, bool $unlock_child = false): void
    {
        // unlocking a category => all its parent categories become unlocked
        if ($visible) {
            $album_ids = $this->getUppercatIds($ids);
            if ($unlock_child) {
                $album_ids = array_merge($album_ids, $this->albumRepository->getSubcatIds($ids));
            }

            $this->albumRepository->updateAlbums(['visible' => true], $album_ids);
        } else { // locking a category   => all its child categories become locked
            $this->albumRepository->updateAlbums(['visible' => false], $this->albumRepository->getSubcatIds($ids));
        }
    }

    /**
     * Orders albums (update albums.rank and global_rank database fields)
     * so that rank field are consecutive integers starting at 1 for each child.
     */
    public function updateGlobalRank(): void
    {
        $albums = [];
        $current_rank = 0;
        $current_parent = null;

        foreach ($this->getCacheAlbums() as $id => $album) {
            if (!is_null($current_parent) && $album->getParent()->getId() !== $current_parent->getId()) {
                $current_rank = 0;
                $current_parent = $album->getParent();
            }
            $current_rank++;
            $albums[$id] = [
                'rank' => $album->getRank(),
                'rank_changed' => $current_rank !== $album->getRank(),
                'global_rank' => $album->getGlobalRank(),
                'uppercats' => $album->getUppercats(),
            ];
        }

        $map_callback = fn ($m) => $albums[$m[1]]['rank'];

        foreach ($albums as $id => $album) {
            /** @phpstan-ignore-next-line */
            $new_global_rank = preg_replace_callback('/(\d+)/', $map_callback, str_replace(',', '.', (string) $album['uppercats']));

            if ($album['rank_changed'] || $new_global_rank !== $album['global_rank']) {
                $album_to_update = $this->getCacheAlbums()[$id];
                $album_to_update->setRank($album['rank']);
                $album_to_update->setGlobalRank($new_global_rank);
                $this->albumRepository->addOrUpdateAlbum($album_to_update);
            }
        }

        unset($albums);
    }

    /**
     * @param int[] $ids
     *
     * @return array<int, DateTime|null>
     */
    public function getAlbumsRefDate(array $ids, string $field = 'date_available', string $minmax = 'max'): array
    {
        // we need to work on the whole tree under each category, even if we don't want to sort sub categories
        $album_ids = $this->albumRepository->getSubcatIds($ids);

        // search for the reference date of each album
        $ref_dates = [];
        foreach ($this->imageRepository->getReferenceDateForAlbums($field, $minmax, $album_ids) as $image) {
            $ref_dates[$image['album_id']] = new DateTime($image['ref_date']);
        }

        // then iterate on all albums (having a ref_date or not) to find the reference_date, with a search on sub-albums
        $uppercats_of = [];
        foreach ($this->albumRepository->findBy(['id' => $album_ids]) as $album) {
            $uppercats_of[$album->getId()] = $album;
        }

        foreach (array_keys($uppercats_of) as $album_id) {
            // find the subcats
            $subcat_ids = [];

            foreach ($uppercats_of as $id => $album) {
                if (preg_match('/(^|,)' . $album_id . '(,|$)/', (string) $album->getUppercats())) {
                    $subcat_ids[] = $id;
                }
            }

            $to_compare = [];
            foreach ($subcat_ids as $id) {
                if (isset($ref_dates[$id])) {
                    $to_compare[] = $ref_dates[$id];
                }
            }

            if ($to_compare !== []) {
                $ref_dates[$album_id] = 'max' === $minmax ? max($to_compare) : min($to_compare);
            } else {
                $ref_dates[$album_id] = null;
            }
        }

        // only return the list of $ids, not the sub-categories
        $return = [];
        foreach ($ids as $id) {
            $return[$id] = $ref_dates[$id];
        }

        return $return;
    }

    /**
     * Set a new random representant to the albums.
     * @param int[] $album_ids
     */
    public function setRandomRepresentant(array $album_ids): void
    {
        foreach ($album_ids as $album_id) {
            $representative = $this->imageAlbumRepository->findRandomRepresentant($album_id);

            // @TODO: find a way to do massive update
            $this->albumRepository->updateAlbumRepresentative($album_id, $representative);
        }
    }

    /**
     * Returns display text for images counter of album
     * $album_nb_images nb images directly in album
     * $album_count_images nb images in album (including sub-albums)
     * $album_count_albums nb sub-albums
     * $short_message if true append " in this album"
     */
    public function getDisplayImagesCount(int $album_nb_images, int $album_count_images, int $album_count_albums, bool $short_message = true, string $separator = "\n"): string
    {
        $display_text = '';

        if ($album_count_images > 0) {
            if ($album_nb_images > 0 && $album_nb_images < $album_count_images) {
                $display_text .= $this->getDisplayImagesCount($album_nb_images, $album_nb_images, 0, $short_message, $separator) . $separator;
                $album_count_images -= $album_nb_images;
                $album_nb_images = 0;
            }

            //at least one image direct or indirect
            $display_text .= $this->translator->trans('number_of_photos', ['count' => $album_count_images]);

            if ($album_count_albums === 0 || $album_nb_images === $album_count_images) {
                //no descendant categories or descendants do not contain images
                if (!$short_message) {
                    $display_text .= ' ' . $this->translator->trans('in this album');
                }
            } else {
                $display_text .= ' ' . $this->translator->trans('number_of_photos_in_sub_albums', ['count' => $album_count_albums]);
            }
        }

        return $display_text;
    }

    // To remove ?

    /**
     * Verifies that the representative picture really exists in the db and
     * picks up a random representative if possible and based on config.
     *
     * @param int[] $ids
     */
    public function updateAlbums(array $ids = []): void
    {
        // find all albums where the setted representative is not possible : the picture does not exist
        $wrong_representants = $this->getRepository()->findWrongRepresentant($ids);

        // remove userCache for albums
        $this->userCacheAlbumRepository->deleteForAlbums($ids);

        if (count($wrong_representants) > 0) {
            $this->getRepository()->updateAlbums(['representative_picture_id' => null], $wrong_representants);
        }

        if (!$this->conf['allow_random_representative']) {
            // If the random representant is not allowed, we need to find
            // albums with elements and with no representant. Those albums
            // must be added to the list of albums to set to a random representant.
            $to_rand = $this->getRepository()->findNeedeedRandomRepresentant($ids);

            if (count($to_rand) > 0) {
                $this->setRandomRepresentant($to_rand);
            }
        }
    }

    /**
     * Recursively deletes one or more albums.
     * It also deletes :
     *    - all the elements physically linked to the album (with ImageMapper::deleteElements)
     *    - all the links between elements and this album
     *    - all the restrictions linked to the album
     *
     * @param int[] $ids
     */
    public function deleteAlbums(array $ids = []): void
    {
        if ($ids === []) {
            return;
        }

        // add sub-albums ids to the given ids : if an album is deleted, all sub-albums must be so
        $ids = array_merge($ids, $this->getRepository()->getSubcatIds($ids));

        foreach ($this->getRepository()->findBy(['id' => $ids]) as $album) {
            $album->clearAllGroupAccess();
            $album->clearAllUserAccess();
        }

        // destruction of the links between images and that album
        $this->imageAlbumRepository->deleteByAlbum($ids);

        // destruction of the albums
        $this->userCacheAlbumRepository->deleteForAlbums($ids);
        $this->getRepository()->deleteAlbums($ids);
    }

    /**
     * Associate a list of images to a list of albums.
     * The function will not duplicate links and will preserve ranks.
     *
     * @param int[] $image_ids
     * @param int[] $album_ids
     */
    public function associateImagesToAlbums(array $image_ids, array $album_ids): void
    {
        // get max rank of each albums
        $current_rank_of = $this->imageAlbumRepository->findMaxRankForEachAlbums($album_ids);

        $albums = $this->getRepository()->findBy(['id' => $album_ids]);
        $images = $this->imageRepository->findBy(['id' => $image_ids]);

        foreach ($albums as $album) {
            foreach ($images as $i => $image) {
                $imageAlbum = new ImageAlbum();
                $imageAlbum->setAlbum($album);
                $imageAlbum->setImage($image);
                $imageAlbum->setRank($current_rank_of[$album->getId()] ?? $i);

                $album->addImageAlbum($imageAlbum);
            }
            $this->albumRepository->addOrUpdateAlbum($album);
        }

        $this->updateAlbums($album_ids);
        $this->userCacheAlbumRepository->deleteForAlbums($album_ids);
    }

    /**
     * Dissociate images from all old albums except their storage album and
     * associate to new albums.
     * This methods will preserve ranks.
     * @param int[] $image_ids
     * @param int[] $album_ids
     */
    public function moveImagesToAlbums(array $image_ids = [], array $album_ids = []): void
    {
        if ($image_ids === []) {
            return;
        }

        $new_album_ids = [];
        foreach ($this->imageRepository->findWithNoStorageOrStorageForAlbums($album_ids) as $image) {
            $new_album_ids[] = $image->getStorageCategoryId();
        }

        // let's first break links with all old albums but their "storage album"
        $this->imageAlbumRepository->deleteByAlbum($album_ids, $image_ids);

        if ($new_album_ids !== []) {
            $this->associateImagesToAlbums($image_ids, $new_album_ids);
        }
    }

    /**
     * save the rank depending on given albums order
     *
     * The list of ordered albums id is supposed to be in the same parent album
     *
     * @param array<array<string, mixed>> $albums
     */
    public function saveAlbumsOrder(array $albums): void
    {
        $current_rank_for_id_uppercat = [];
        $current_rank = 0;

        foreach ($albums as $album) {
            if (is_array($album)) {
                $id = $album['id'];
                $id_uppercat = $album['id_uppercat'];

                if (!isset($current_rank_for_id_uppercat[$id_uppercat])) {
                    $current_rank_for_id_uppercat[$id_uppercat] = 0;
                }
                $current_rank = ++$current_rank_for_id_uppercat[$id_uppercat];
            } else {
                $id = $album;
                $current_rank++;
            }

            $this->getCacheAlbums()[$id]->setRank($current_rank);
            $this->getRepository()->addOrUpdateAlbum($this->getCacheAlbums()[$id]);
        }

        $this->updateGlobalRank();
    }

    /**
     * @param Album[] $albums
     *
     * @return array<mixed>
     */
    public function getAlbumThumbnails(User $user, array $albums): array
    {
        $album_thumbnails = [];
        $user_representative_updates_for = [];
        $image_ids = [];

        foreach ($albums as $album) {
            $userCacheAlbum = $album->getUserCacheAlbum();
            if (!$userCacheAlbum) {
                continue;
            }

            if ($userCacheAlbum->getUserRepresentativePicture()) {
                $image_id = $userCacheAlbum->getUserRepresentativePicture();
            } elseif ($album->getRepresentativePictureId()) { // if a representative picture is set, it has priority
                $image_id = $album->getRepresentativePictureId();
            } elseif ($this->conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
                if (($random_image = $this->imageRepository->getRandomImageInAlbum($album->getId(), $album->getUppercats(), $user->getUserInfos()->getForbiddenAlbums())) instanceof Image) {
                    $image_id = $random_image->getId();
                }
            } elseif ($userCacheAlbum->getCountAlbums() > 0 && $userCacheAlbum->getCountImages() > 0) { // searching a random representant among representant of sub-categories
                if (($random_image = $this->getRepository()->findRandomRepresentantAmongSubAlbums($album->getUppercats())) instanceof Album) {
                    $image_id = $random_image->getId();
                }
            }

            if (isset($image_id)) {
                if ($this->conf['representative_cache_on_subcats'] && $userCacheAlbum->getUserRepresentativePicture() !== $image_id) {
                    $user_representative_updates_for[$album->getId()] = $image_id;
                }

                $album->setRepresentativePictureId($image_id);
                $album_thumbnails[$album->getId()] = $album;
                $image_ids[] = $image_id;
            }
            unset($image_id);
        }

        usort($album_thumbnails, self::globalRankCompare(...));

        return [$album_thumbnails, $image_ids, $user_representative_updates_for];
    }

    /**
     * @param Album[] $albums
     * @param int[] $image_ids
     *
     * @return array<string, mixed>
     */
    public function getInfosOfImages(User $user, array $albums, array $image_ids, ImageMapper $imageMapper): array
    {
        $infos_of_images = [];
        $bad_level_ids = [];

        foreach ($imageMapper->getRepository()->findBy(['id' => $image_ids]) as $image) {
            if ($image->getLevel() <= $user->getUserInfos()->getLevel()) {
                $infos_of_images[$image->getId()] = [$image->toArray(), 'image' => $image];
            } else {
                $bad_level_ids[] = $image->getId();
            }
        }

        $missing_image_ids = array_values(array_diff($image_ids, $bad_level_ids, array_keys($infos_of_images)));
        // problem: we must not display the thumbnail of a photo which has a higher privacy level than user privacy level
        foreach ($missing_image_ids as $id) {
            // * what is the represented album?
            // * find a random photo matching user permissions
            // * register it at user_representative_picture_id
            // * set it as the representative_picture_id for the album

            foreach ($albums as $album) {
                if ($album->getRepresentativePictureId() === $id) {
                    // searching a random representant among elements in sub-categories
                    $random_image = $this->imageRepository->getRandomImageInAlbum($album->getId(), $album->getUppercats(), $user->getUserInfos()->getForbiddenAlbums());

                    if (!is_null($random_image) && !in_array($random_image->getId(), $image_ids)) {
                        $infos_of_images[$random_image->getId()] = [$random_image->toArray(), 'image' => $random_image];

                        if ($this->conf['representative_cache_on_level']) {
                            // @TODO save representative picture in cache
                        }

                        $album->setRepresentativePictureId($random_image->getId());
                    }

                    $this->getRepository()->addOrUpdateAlbum($album);
                }
            }
        }

        // problem: we must not display the thumbnail of a photo which has a higher privacy level than user privacy level
        foreach ($bad_level_ids as $id) {
            // * what is the represented album?
            // * find a random photo matching user permissions
            // * register it at user_representative_picture_id
            // * set it as the representative_picture_id for the album

            foreach ($albums as $album) {
                if ($id === $album->getRepresentativePictureId()) {
                    // searching a random representant among elements in sub-categories
                    $random_image = $this->imageRepository->getRandomImageInAlbum($album->getId(), $album->getUppercats(), $user->getUserInfos()->getForbiddenAlbums());

                    if (!is_null($random_image) && !in_array($random_image->getId(), $image_ids)) {
                        $infos_of_images[$random_image->getId()] = [$random_image->toArray(), 'image' => $random_image];

                        if ($this->conf['representative_cache_on_level']) {
                            // @TODO save representative picture in cache
                        }

                        $album->setRepresentativePictureId($random_image->getId());
                    }

                    $this->getRepository()->addOrUpdateAlbum($album);
                }
            }
        }

        return $infos_of_images;
    }
}
