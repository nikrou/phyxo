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

use App\Entity\Album;
use App\Entity\ImageAlbum;
use App\Entity\User;
use App\Entity\UserCacheAlbum;
use App\Repository\AlbumRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageRepository;
use App\Repository\UserCacheAlbumRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\Image\SrcImage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;

class AlbumMapper
{
    private $conf, $albumRepository, $router, $cache = [], $albums_retrieved = false, $translator, $userRepository, $userCacheAlbumRepository, $imageAlbumRepository, $imageRepository;

    public function __construct(Conf $conf, AlbumRepository $albumRepository, RouterInterface $router, TranslatorInterface $translator, UserRepository $userRepository,
                                UserCacheAlbumRepository $userCacheAlbumRepository, ImageAlbumRepository $imageAlbumRepository, ImageRepository $imageRepository)
    {
        $this->conf = $conf;
        $this->albumRepository = $albumRepository;
        $this->router = $router;
        $this->translator = $translator;
        $this->userRepository = $userRepository;
        $this->userCacheAlbumRepository = $userCacheAlbumRepository;
        $this->imageAlbumRepository = $imageAlbumRepository;
        $this->imageRepository = $imageRepository;
    }

    public function getRepository(): AlbumRepository
    {
        return $this->albumRepository;
    }

    /**
     * Returns template vars for main albums menu.
     *
     */
    public function getRecursiveAlbumsMenu(User $user, array $selected_category = []): array
    {
        $flat_categories = $this->getAlbumsMenu($user, $selected_category);

        $categories = [];
        foreach ($flat_categories as $category) {
            if ($category['uppercats'] == $category['id']) {
                $categories[$category['id']] = $category;
            } else {
                $this->insertAlbumInTree($categories, $category, $category['uppercats']);
            }
        }

        return $categories;
    }

    protected function insertAlbumInTree(&$categories, $category, $uppercats)
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
     *
     */
    protected function getAlbumsMenu(User $user, array $selected_category = []): array
    {
        $albums = [];
        foreach ($this->getRepository()->getAlbumsForMenu($user->getId(), $user->getUserInfos()->getForbiddenCategories()) as $album) {
            $album_infos = array_merge(
                $album->toArray(),
                [
                    'NAME' => $album->getName(),
                    'TITLE' => $this->getDisplayImagesCount($album->getUserCacheAlbums()->first()->getNbImages(), $album->getUserCacheAlbums()->first()->getCountImages(),
                                    $album->getUserCacheAlbums()->first()->getCountAlbums(), false, ' / '
                                ),
                    'URL' => $this->router->generate('album', ['category_id' => $album->getId()]),
                    'LEVEL' => substr_count($album->getGlobalRank(), '.') + 1,
                    'SELECTED' => isset($selected_category['id']) && $selected_category['id'] === $album->getId() ? true : false,
                    'IS_UPPERCAT' => isset($selected_category['id_uppercat']) && $selected_category['id_uppercat'] === $album->getId() ? true : false,
                    'count_images' => $album->getUserCacheAlbums()->first()->getCountImages(),
                    'icon_ts' => ''
                ]
            );
            if ($this->conf['index_new_icon'] && !empty($row['max_date_last'])) { // @FIX : cf BUGS
                // $row['icon_ts'] = $this->em->getRepository(BaseRepository::class)->getIcon($row['max_date_last'], $user, $child_date_last);
            }
            $albums[$album->getId()] = $album_infos;
        }
        uasort($albums, '\Phyxo\Functions\Utils::global_rank_compare');

        return $albums;
    }

    /**
     * Get computed array of albums, that means cache data of all albums
     * available for the current user (count_categories, count_images, etc.).
     */
    public function getComputedAlbums(int $level, array $forbidden_categories = [])
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
            if (empty($album['id_uppercat'])) {
                continue;
            }

            if (empty($albums[$album['id_uppercat']])) {
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

                if (!isset($parent['id_uppercat'])) {
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
     */
    public function removeComputedAlbum(array $albums, $album): array
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
     * Generates breadcrumb from categories list.
     * Categories string returned contains categories as given in the input
     * array $cat_informations. $cat_informations array must be an array
     * of array( id=>?, name=>?). If url input parameter is null,
     * returns only the categories name without links.
     */
    public function getAlbumDisplayName(array $cat_informations, string $url = ''): string
    {
        $output = '';
        $is_first = true;

        foreach ($cat_informations as $cat) {
            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $this->conf['level_separator'];
            }

            if (empty($url)) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . $this->router->generate('album', ['category_id' => $cat['id']]) . '">';
                $output .= $cat['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $cat['id'] . '">';
                $output .= $cat['name'] . '</a>';
            }
        }

        return $output;
    }

    public function getAlbumsDisplayName(string $uppercats, string $route_name, array $params = []): array
    {
        $names = [];

        foreach (explode(',', $uppercats) as $category_id) {
            $names[] = [
                'name' => $this->getCacheAlbums()[$category_id]->getName(),
                'url' => $this->router->generate($route_name, array_merge($params, ['album_id' => $this->getCacheAlbums()[$category_id]->getId()]))
            ];
        }

        return $names;
    }

    public function getBreadcrumb(Album $album): array
    {
        $breadcumb = [];

        $upper_names = [];
        $upper_ids = explode(',', $album->getUppercats());
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
                    'name' => $this->getCacheAlbums()[$album_id]->getName(),
                ];
            }
        }

        foreach ($upper_names as $album) {
            $breadcumb[] = [
                'url' => $this->router->generate('album', ['category_id' => $album['id']]),
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
        $output = '';
        if ($single_link) {
            $single_url = $this->router->generate('album', ['category_id' => array_pop(explode(',', $uppercats)) ]);
            $output .= '<a href="' . $single_url . '"';
            if (!empty($link_class)) {
                $output .= ' class="' . $link_class . '"';
            }
            $output .= '>';
        }

        // @TODO: refactoring with getAlbumDisplayName
        $is_first = true;
        foreach (explode(',', $uppercats) as $album_id) {
            $album = $this->getCacheAlbums()[$album_id];

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= ' / ';
            }

            if ($url === '' || $single_link) {
                $output .= $album->getName();
            } else {
                $output .= '<a href="' . $this->router->generate('album', ['category_id' => $album->getId()]) . '">' . $album->getName() . '</a>';
            }
        }

        if ($single_link && isset($single_url)) {
            $output .= '</a>';
        }

        return $output;
    }

    public function displaySelectAlbums(array $albums, array $selecteds, string $blockname, bool $fullname = true)
    {
        $tpl_cats = [];
        foreach ($albums as $album) {
            if ($fullname) {
                $option = strip_tags($this->getAlbumsDisplayNameCache($album->getUppercats()));
            } else {
                $option = str_repeat('&nbsp;', (3 * substr_count($album->getGlobalRank(), '.')));
                $option .= '- ';
                $option .= strip_tags($album->getName());
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
     */
    public function displaySelectAlbumsWrapper(array $albums, array $selecteds, string $blockname, bool $fullname = true): array
    {
        usort($albums, [$this, 'globalRankCompare']);

        return $this->displaySelectAlbums($albums, $selecteds, $blockname, $fullname);
    }

    /**
     * Change the parent album of the given albums. The albums are supposed virtual.
     */
    public function moveAlbums(array $ids, int $new_parent = null)
    {
        if (count($ids) === 0) {
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
                if (preg_match('/^' . $album['uppercats'] . '(,|$)/', $new_parent_uppercats)) {
                    throw new \Exception($this->translator->trans('You cannot move an album in its own sub album'));
                }
            }
        }

        if ($new_parent != null) {
            foreach ($ids as $id) {
                $this->getCacheAlbums()[$id]->setParent($this->getCacheAlbums()[$new_parent]);
            }
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
    public function updateUppercats()
    {
        foreach ($this->getCacheAlbums() as $id => $album) {
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
     */
    public function setAlbumsStatus(array $album_ids, string $status)
    {
        if (!in_array($status, [Album::STATUS_PUBLIC, Album::STATUS_PRIVATE])) {
            throw new \Exception("AlbumMapper::setAlbumsStatus invalid param $status");
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
            foreach ($this->albumRepository->findById($album_ids) as $album) {
                $all_albums[] = $album;
            }
            usort($all_albums, [$this, 'globalRankCompare']);

            foreach ($all_albums as $album) {
                $is_top = true;

                if ($album->getParent()) {
                    foreach (explode(',', $album->getUppercats()) as $id_uppercat) {
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
            $parent_albums = [];

            if (count($parent_ids) > 0) {
                foreach ($this->albumRepository->findById($parent_ids) as $album) {
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
     */
    public function getUppercatIds(array $ids): array
    {
        if (count($ids) < 1) {
            return [];
        }

        $uppercats = [];
        foreach ($ids as $id) {
            $uppercats = array_merge($uppercats, explode(',', $this->getCacheAlbums()[$id]->getUppercats()));
        }

        return array_unique($uppercats);
    }

    /**
     * Grant access to a list of categories for a list of users.
     */
    public function addPermissionOnAlbum(array $album_ids, array $user_ids, bool $apply_on_sub = false)
    {
        // check for emptiness
        if (count($album_ids) === 0 || count($user_ids) === 0) {
            return;
        }

        // make sure albums are private and select uppercats or subcats
        $cat_ids = $this->getUppercatIds($album_ids);
        if ($apply_on_sub) {
            $cat_ids = array_merge($cat_ids, $this->albumRepository->getSubcatIds($album_ids));
        }

        if (count($cat_ids) > 0) {
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
     * @param array $options
     *    - boolean commentable
     *    - boolean visible
     *    - string status
     *    - string comment
     *    - boolean inherit
     */
    public function createAlbum(string $name, ?Album $parent = null, int $user_id, array $admin_ids = [], array $options = []): Album
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
        $album->setLastModified(new \DateTime());

        $album_id = $this->albumRepository->addOrUpdateAlbum($album);
        if (!is_null($parent)) {
            $album->setUppercats($parent->getUppercats() . ',' . $album_id);
        } else {
            $album->setUppercats($album_id);
            $album->setGlobalRank($album_id);
        }
        $album_id = $this->albumRepository->addOrUpdateAlbum($album);

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
    public function globalRankCompare(Album $a, Album $b)
    {
        return strnatcasecmp($a->getGlobalRank(), $b->getGlobalRank());
    }

    protected function getCacheAlbums()
    {
        if (!$this->albums_retrieved) {
            foreach ($this->albumRepository->findAll() as $album) {
                $this->cache[$album->getId()] = $album;
            }
        }

        return $this->cache;
    }

    /**
     * Change the **visible** property on a set of albums.
     */
    public function setAlbumsVisibility(array $ids, bool $visible, $unlock_child = false)
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
    public function updateGlobalRank()
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
                'rank' => $current_rank,
                'rank_changed' => $current_rank !== $album->getRank(),
                'global_rank' => $album->getGlobalRank(),
                'uppercats' => $album->getUppercats(),
            ];
        }

        $map_callback = function ($m) use ($albums) {
            return $albums[$m[1]]['rank'];
        };

        foreach ($albums as $id => $album) {
            $new_global_rank = preg_replace_callback(
                '/(\d+)/',
                $map_callback,
                str_replace(',', '.', $album['uppercats'])
            );

            if ($album['rank_changed'] || $new_global_rank !== $album['global_rank']) {
                $album_to_update = $this->getCacheAlbums()[$id];
                $album_to_update->setRank($album['rank']);
                $album_to_update->setGlobalRank($new_global_rank);
                $this->albumRepository->addOrUpdateAlbum($album_to_update);
            }
        }

        unset($albums);
    }

    public function getAlbumsRefDate(array $ids, string $field = 'date_available', string $minmax = 'max')
    {
        // we need to work on the whole tree under each category, even if we don't want to sort sub categories
        $album_ids = $this->albumRepository->getSubcatIds($ids);

        // search for the reference date of each album
        $ref_dates = [];
        foreach ($this->imageRepository->getReferenceDateForAlbums($field, $minmax, $album_ids) as $image) {
            $ref_dates[$image['album_id']] = new \DateTime($image['ref_date']);
        }

        // then iterate on all albums (having a ref_date or not) to find the reference_date, with a search on sub-albums
        $uppercats_of = [];
        foreach ($this->albumRepository->findById($album_ids) as $album) {
            $uppercats_of[$album->getId()] = $album;
        }

        foreach ($uppercats_of as $album_id) {
            // find the subcats
            $subcat_ids = [];

            foreach ($uppercats_of as $id => $uppercats) {
                if (preg_match('/(^|,)' . $album_id . '(,|$)/', $uppercats)) {
                    $subcat_ids[] = $id;
                }
            }

            $to_compare = [];
            foreach ($subcat_ids as $id) {
                if (isset($ref_dates[$id])) {
                    $to_compare[] = $ref_dates[$id];
                }
            }

            if (count($to_compare) > 0) {
                $ref_dates[$album_id] = 'max' == $minmax ? max($to_compare) : min($to_compare);
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
     */
    public function setRandomRepresentant(array $album_ids)
    {
        foreach ($album_ids as $album_id) {
            $representative = $this->imageAlbumRepository->findRandomRepresentant($album_id);

            // @TODO: find a way to do massive update
            $this->albumRepository->updateAlbumRepresentative($album_id, $representative);
        }
    }

    /**
     * Returns display text for images counter of album
     *
     * @param int $cat_nb_images nb images directly in album
     * @param int $cat_count_images nb images in album (including subcats)
     * @param int $cat_count_categories nb subcats
     * @param bool $short_message if true append " in this album"
     * @param string $separator
     * @return string
     */
    public function getDisplayImagesCount($cat_nb_images, $cat_count_images, $cat_count_categories, $short_message = true, $separator = '\n')
    {
        $display_text = '';

        if ($cat_count_images > 0) {
            if ($cat_nb_images > 0 and $cat_nb_images < $cat_count_images) {
                $display_text .= $this->getDisplayImagesCount($cat_nb_images, $cat_nb_images, 0, $short_message, $separator) . $separator;
                $cat_count_images -= $cat_nb_images;
                $cat_nb_images = 0;
            }

            //at least one image direct or indirect
            $display_text .= $this->translator->trans('number_of_photos', ['count' => $cat_count_images]);

            if ($cat_count_categories === 0 || $cat_nb_images === $cat_count_images) {
                //no descendant categories or descendants do not contain images
                if (!$short_message) {
                    $display_text .= ' ' . $this->translator->trans('in this album');
                }
            } else {
                $display_text .= ' ' . $this->translator->trans('number_of_photos_in_sub_albums', ['count' => $cat_count_categories]);
            }
        }

        return $display_text;
    }

    // To remove ?

    /**
     * Verifies that the representative picture really exists in the db and
     * picks up a random representative if possible and based on config.
     */
    public function updateAlbums(array $ids = [])
    {
        // find all albums where the setted representative is not possible : the picture does not exist
        $wrong_representants = $this->getRepository()->findWrongRepresentant($ids);

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
     */
    public function deleteAlbums(array $ids = [])
    {
        if (count($ids) === 0) {
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
     */
    public function associateImagesToAlbums(array $image_ids, array $album_ids)
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
     */
    public function moveImagesToAlbums(array $image_ids = [], array $album_ids = [])
    {
        if (count($image_ids) === 0) {
            return false;
        }

        $new_album_ids = [];
        foreach ($this->imageRepository->findWithNoStorageOrStorageForAlbums($album_ids) as $image) {
            $new_album_ids[] = $image->getStorageCategoryId();
        }

        // let's first break links with all old albums but their "storage album"
        $this->imageAlbumRepository->deleteByAlbum($album_ids, $image_ids);

        if (count($new_album_ids) > 0) {
            $this->associateImagesToAlbums($image_ids, $new_album_ids);
        }
    }

    /**
     * save the rank depending on given albums order
     *
     * The list of ordered albums id is supposed to be in the same parent album
     */
    public function saveAlbumsOrder(array $categories)
    {
        $current_rank_for_id_uppercat = [];
        $current_rank = 0;

        foreach ($categories as $category) {
            if (is_array($category)) {
                $id = $category['id'];
                $id_uppercat = $category['id_uppercat'];

                if (!isset($current_rank_for_id_uppercat[$id_uppercat])) {
                    $current_rank_for_id_uppercat[$id_uppercat] = 0;
                }
                $current_rank = ++$current_rank_for_id_uppercat[$id_uppercat];
            } else {
                $id = $category;
                $current_rank++;
            }

            $this->getCacheAlbums()[$id]->setRank($current_rank);
            $this->getRepository()->addOrUpdateAlbum($this->getCacheAlbums()[$id]);
        }

        $this->updateGlobalRank();
    }

    /**
     * param $albums resource (array or null) return by method repository
     */
    public function getAlbumThumbnails(User $user, $albums)
    {
        $album_thumbnails = [];
        $image_ids = [];
        $is_child_date_last = false;

        foreach ($albums as $album) {
            $userCacheAlbum = $user->getUserCacheAlbums()->filter(function(UserCacheAlbum $uca) use ($album) {
                return $uca->getAlbum()->getId() === $album->getId();
            })->first();

            if (!$userCacheAlbum) {
                continue;
            }

            $is_child_date_last = $userCacheAlbum->getMaxDateLast() > $userCacheAlbum->getDateLast();

            if ($userCacheAlbum->getUserRepresentativePicture()) {
                $image_id = $userCacheAlbum->getUserRepresentativePicture();
            } elseif ($album->getRepresentativePictureId()) { // if a representative picture is set, it has priority
                $image_id = $album->getRepresentativePictureId();
            } elseif ($this->conf['allow_random_representative']) { // searching a random representant among elements in sub-categories
                if ($random_image = $this->getRepository()->getRandomImageInAlbum($album->getId(), $album->getUppercats(), $user->getUserInfos()->getForbiddenCategories())) {
                    $image_id = $random_image->getId();
                }
            } elseif ($userCacheAlbum->getCountAlbums() > 0 && $userCacheAlbum->getCountImages() > 0) { // searching a random representant among representant of sub-categories
                if ($random_image = $this->getRepository()->findRandomRepresentantAmongSubAlbums($album->getUppercats())) {
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

        usort($album_thumbnails, [$this, 'globalRankCompare']);

        return [$is_child_date_last, $album_thumbnails, $image_ids];
    }

    public function getInfosOfImages(User $user, array $albums, array $image_ids, ImageMapper $imageMapper)
    {
        $infos_of_images = [];
        $new_image_ids = [];

        foreach ($imageMapper->getRepository()->findBy(['id' => $image_ids]) as $image) {
            if ($image->getLevel() <= $user->getUserInfos()->getLevel()) {
                $infos_of_images[$image->getId()] = $image->toArray();
            } else {
                // problem: we must not display the thumbnail of a photo which has a
                // higher privacy level than user privacy level
                //
                // * what is the represented album?
                // * find a random photo matching user permissions
                // * register it at user_representative_picture_id
                // * set it as the representative_picture_id for the album

                foreach ($albums as $album) {
                    if ($image->getId() === $album->getRepresentativePictureId()) {
                        // searching a random representant among elements in sub-categories
                        if ($random_image = $this->getRepository()->getRandomImageInAlbum($album->getId(), $album->getUppercats(), $user->getUserInfos()->getForbiddenCategories())) {
                            $image_id = $random_image->getId();
                        }

                        if (isset($image_id) && !in_array($image_id, $image_ids)) {
                            $new_image_ids[] = $image_id;

                            if ($this->conf['representative_cache_on_level']) {
                                $user_representative_updates_for[$album->getId()] = $image_id;
                            }

                            $album->setRepresentativePictureId($image_id);
                        }

                        $this->getRepository()->addOrUpdateAlbum($album);
                    }
                }
            }
        }

        if (count($new_image_ids) > 0) {
            foreach ($imageMapper->getRepository()->findBy(['id' => $new_image_ids]) as $image) {
                $infos_of_images[$image->getId()] = $image->toArray();
            }
        }


        foreach ($infos_of_images as &$info) {
            $info['src_image'] = new SrcImage($info, $this->conf['picture_ext']);
        }
        unset($info);

        return $infos_of_images;
    }
}
