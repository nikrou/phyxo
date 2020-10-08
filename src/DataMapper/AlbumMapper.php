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
use App\Repository\AlbumRepository;
use App\Repository\BaseRepository;
use App\Repository\CategoryRepository;
use App\Repository\GroupAccessRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\OldPermalinkRepository;
use App\Repository\UserAccessRepository;
use App\Repository\UserCacheCategoriesRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AlbumMapper
{
    private $conf, $albumRepository, $router, $cache = [], $albums_retrieved = false, $em, $translator;

    public function __construct(Conf $conf, AlbumRepository $albumRepository, RouterInterface $router, TranslatorInterface $translator, EntityManager $em)
    {
        $this->conf = $conf;
        $this->albumRepository = $albumRepository;
        $this->router = $router;
        $this->translator = $translator;
        $this->em = $em;
    }

    public function getRepository(): AlbumRepository
    {
        return $this->albumRepository;
    }

    /**
     * Returns template vars for main albums menu.
     *
     */
    public function getRecursiveAlbumsMenu(UserInterface $user, array $filter = [], array $selected_category = []): array
    {
        $flat_categories = $this->getAlbumsMenu($user, $filter, $selected_category);

        $categories = [];
        foreach ($flat_categories as $category) {
            if ($category['uppercats'] === $category['id']) {
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
    protected function getAlbumsMenu(UserInterface $user, array $filter = [], array $selected_category = []): array
    {
        $result = $this->em->getRepository(CategoryRepository::class)->getCategoriesForMenu(
            $user,
            $filter,
            isset($selected_category['uppercats']) ? explode(',', $selected_category['uppercats']) : []
        );

        $cats = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $child_date_last = (isset($row['max_date_last'], $row['date_last']) && ($row['max_date_last'] > $row['date_last']));

            $row = array_merge(
                $row,
                [
                    'NAME' => $row['name'],
                    'TITLE' => $this->getDisplayImagesCount($row['nb_images'], $row['count_images'], $row['count_categories'], false, ' / '),
                    'URL' => $this->router->generate('album', ['category_id' => $row['id']]),
                    'LEVEL' => substr_count($row['global_rank'], '.') + 1,
                    'SELECTED' => isset($selected_category['id']) && $selected_category['id'] === $row['id'] ? true : false,
                    'IS_UPPERCAT' => isset($selected_category['id_uppercat']) && $selected_category['id_uppercat'] === $row['id'] ? true : false,
                ]
            );
            if ($this->conf['index_new_icon'] && !empty($row['max_date_last'])) { // @FIX : cf BUGS
                $row['icon_ts'] = $this->em->getRepository(BaseRepository::class)->getIcon($row['max_date_last'], $user, $child_date_last);
            }
            $cats[$row['id']] = $row;
        }
        uasort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

        return $cats;
    }

    /**
     * Get computed array of albums, that means cache data of all albums
     * available for the current user (count_categories, count_images, etc.).
     */
    public function getComputedAlbums($userdata, $filter_days = null)
    {
        $result = $this->em->getRepository(CategoryRepository::class)->getComputedCategories($userdata, $filter_days);
        $userdata['last_photo_date'] = null;
        $cats = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $row['nb_categories'] = 0;
            $row['count_categories'] = 0;
            $row['count_images'] = (int)$row['nb_images'];
            $row['max_date_last'] = $row['date_last'];
            if ($row['date_last'] > $userdata['last_photo_date']) {
                $userdata['last_photo_date'] = $row['date_last'];
            }

            $cats[$row['cat_id']] = $row;
        }

        foreach ($cats as $cat) {
            if (!isset($cat['id_uppercat'])) {
                continue;
            }

            if (!isset($cats[ $cat['id_uppercat'] ])) {
                continue;
            }

            $parent = &$cats[$cat['id_uppercat']];
            $parent['nb_categories']++;

            do {
                $parent['count_images'] += $cat['nb_images'];
                $parent['count_categories']++;

                if ((empty($parent['max_date_last'])) or ($parent['max_date_last'] < $cat['date_last'])) {
                    $parent['max_date_last'] = $cat['date_last'];
                }

                if (!isset($parent['id_uppercat'])) {
                    break;
                }
                $parent = &$cats[$parent['id_uppercat']];
            } while (true);
            unset($parent);
        }

        if (isset($filter_days)) {
            foreach ($cats as $category) {
                if (empty($category['max_date_last'])) {
                    $this->removeComputedAlbum($cats, $category);
                }
            }
        }

        return $cats;
    }

    /**
     * Removes an album from computed array of albums and updates counters.
     */
    public function removeComputedAlbum(array $cats, $cat): array
    {
        if (isset($cats[$cat['id_uppercat']])) {
            $parent = $cats[$cat['id_uppercat']];
            $parent['nb_categories']--;

            do {
                $parent['count_images'] -= $cat['nb_images'];
                $parent['count_categories'] -= 1 + $cat['count_categories'];

                if (!isset($cats[$parent['id_uppercat']])) {
                    break;
                }
                $parent = $cats[$parent['id_uppercat']];
            } while (true);
        }

        unset($cats[$cat['cat_id']]);

        return $cats;
    }

    /**
     * Generates breadcrumb from categories list.
     * Categories string returned contains categories as given in the input
     * array $cat_informations. $cat_informations array must be an array
     * of array( id=>?, name=>?, permalink=>?). If url input parameter is null,
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
            if (isset($link_class)) {
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

            if (!isset($url) || $single_link) {
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

    /**
     * @param [Album] $albums
     * @param int[] $selected ids of selected items
     * @param string $blockname variable name in template
     * @param bool $fullname full breadcrumb or not
     */
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
        if (count($ids) == 0) {
            return;
        }

        $albums = [];
        foreach ($ids as $id) {
            $albums[$id] = [
                'parent' => $this->getCacheAlbums()[$id]->getIdUppercat(),
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
                if (preg_match('/^' . $album->getUppercats() . '(,|$)/', $new_parent_uppercats)) {
                    throw new \Exception($this->translator->trans('You cannot move an album in its own sub album'));
                }
            }
        }

        $this->albumRepository->updateAlbums(['id_uppercat' => $new_parent], $ids);
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

            $uppercat = $id;
            while ($uppercat) {
                $upper_list[] = $uppercat;
                $uppercat = $this->getCacheAlbums()[$uppercat]->getIdUppercat();
            }

            $new_uppercats = implode(',', array_reverse($upper_list));
            if ($new_uppercats !== $album->getUppercats()) {
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

                if ($album->getIdUppercat()) {
                    foreach (explode(',', $album->getUppercats()) as $id_uppercat) {
                        if (isset($top_albums[$id_uppercat])) {
                            $is_top = false;
                            break;
                        }
                    }
                }

                if ($is_top) {
                    $top_albums[$album->getId()] = $album;

                    if ($album->getIdUppercat()) {
                        $parent_ids[] = $album->getIdUppercat();
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

            $repositories = [
                UserAccessRepository::class => 'user_id',
                GroupAccessRepository::class => 'group_id'
            ];

            foreach ($top_albums as $top_album) {
                // what is the "reference" for list of permissions? The parent album
                // if it is private, else the album itself
                $ref_album_id = $top_album->getId();

                if ($top_album->getIdUppercat() && isset($parent_albums[$top_album->getIdUppercat()]) && $parent_albums[$top_album->getIdUppercat()]->getStatus() === Album::STATUS_PRIVATE) {
                    $ref_album_id = $top_album->getIdUppercat();
                }

                $subalbums = $this->albumRepository->getSubcatIds([$top_album->getId()]);

                foreach ($repositories as $repository => $field) {
                    // what are the permissions user/group of the reference album
                    $result = $this->em->getRepository($repository)->findFieldByCatId($ref_album_id, $field);
                    $ref_access = $this->em->getConnection()->result2array($result, null, $field);

                    if (count($ref_access) == 0) {
                        $ref_access[] = -1;
                    }

                    // step 3, remove the inconsistant permissions from sub-albums
                    $this->em->getRepository($repository)->deleteByCatIds($subalbums, $field . ' NOT ' . $this->em->getConnection()->in($ref_access));
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

        $private_albums = [];
        if (count($cat_ids) > 0) {
            foreach ($this->albumRepository->findByIdsAndStatus($cat_ids, Album::STATUS_PRIVATE) as $album) {
                $private_albums[] = $album->getId();
            }
        }

        if (count($private_albums) === 0) {
            return;
        }

        $inserts = [];
        foreach ($private_albums as $cat_id) {
            foreach ($user_ids as $user_id) {
                if ($user_id > 0) {
                    $inserts[] = [
                        'user_id' => $user_id,
                        'cat_id' => $cat_id
                    ];
                }
            }
        }

        try {
            $this->em->getRepository(UserAccessRepository::class)->insertUserAccess(['user_id', 'cat_id'], $inserts);
        } catch (\Exception $e) {
            // catch possible duplicate entry for user_id/cat_id
        }
    }

    /**
     * Create an album.
     *
     * @param string $category_name
     * @param int $parent_id
     * @param array $options
     *    - boolean commentable
     *    - boolean visible
     *    - string status
     *    - string comment
     *    - boolean inherit
     */
    public function createAlbum(string $name, int $parent_id = null, int $user_id, array $admin_ids = [], array $options = []): int
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

        if (!is_null($parent_id)) {
            $parent = $this->albumRepository->find($parent_id);

            $album->setIdUppercat($parent->getId()); // @TODO: update schema to map parent to Album (auto-join)
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
        if (!is_null($parent_id)) {
            $album->setUppercats($parent->getUppercats() . ',' . $album_id);
        } else {
            $album->setUppercats($album_id);
            $album->setGlobalRank($album_id);
        }
        $album_id = $this->albumRepository->addOrUpdateAlbum($album);

        if ($album->getStatus() === Album::STATUS_PRIVATE) {
            if ($album->getIdUppercat() && (!empty($options['inherit']) || $this->conf['inheritance_by_default'])) {
                $result = $this->em->getRepository(GroupAccessRepository::class)->findFieldByCatId($album->getIdUppercat(), 'group_id');
                $granted_grps = $this->em->getConnection()->result2array($result, null, 'group_id');
                $inserts = [];
                foreach ($granted_grps as $granted_grp) {
                    $inserts[] = ['group_id' => $granted_grp, 'cat_id' => $album_id];
                }
                $this->em->getRepository(GroupAccessRepository::class)->massInserts(['group_id', 'cat_id'], $inserts);

                $result = $this->em->getRepository(UserAccessRepository::class)->findByCatId($album->getIdUppercat());
                $granted_users = $this->em->getConnection()->result2array($result, null, 'user_id');
                $this->addPermissionOnAlbum([$album_id], array_unique(array_merge($admin_ids, [$user_id], $granted_users), $options['apply_on_sub'] ?? false));
            } else {
                $this->addPermissionOnAlbum([$album_id], array_unique(array_merge($admin_ids, [$user_id])), $options['apply_on_sub'] ?? false);
            }
        }
        $this->updateGlobalRank();

        return $album_id;
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
            $this->albumRepository->updateCategories(['visible' => false], $this->albumRepository->getSubcatIds($ids));
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
        $current_uppercat = '';

        foreach ($this->getCacheAlbums() as $id => $album) {
            if ($album->getIdUppercat() !== $current_uppercat) {
                $current_rank = 0;
                $current_uppercat = $album->getIdUppercat();
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
        $result = $this->em->getRepository(ImageRepository::class)->getReferenceDateForCategories($field, $minmax, $album_ids);
        $ref_dates = $this->em->getConnection()->result2array($result, 'category_id', 'ref_date');

        // then iterate on all albums (having a ref_date or not) to find the reference_date, with a search on sub-albums
        $uppercats_of = [];
        foreach ($this->albumRepository->findById($album_ids) as $album) {
            $uppercats_of[$album->getId()] = $album;
        }

        foreach ([$uppercats_of] as $album_id) {
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
     * Returns the fulldir for each given album id.
     */
    public function getFulldirs(array $ids): array
    {
        if (count($ids) == 0) {
            return [];
        }

        $fulldirs = [];
        foreach ($this->albumRepository->findPhysicalAlbums($ids) as $album) {
            $albums[$album->getId()] = $album;
        }

        $dirs_callback = function ($m) use ($albums) {
            return $albums[$m[1]]->getDir();
        };

        foreach ($albums as $album) {
            $uppercats = str_replace(',', '/', $album->getUppercats());
            $fulldirs[$album->getId()] = $album->getSite()->getGalleriesUrl();
            $fulldirs[$album->getId()] .= preg_replace('/(\d+)/', $dirs_callback, $uppercats);
        }

        return $fulldirs;
    }

    /**
     * Set a new random representant to the albums.
     */
    public function setRandomRepresentant(array $album_ids)
    {
        foreach ($album_ids as $album_id) {
            $result = $this->em->getRepository(ImageCategoryRepository::class)->findRandomRepresentant($album_id);
            list($representative) = $this->em->getConnection()->db_fetch_row($result);

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
     *
     * @param 'all'|int|int[] $ids
     */
    public function updateAlbum($ids = 'all')
    {
        if ($ids === 'all') {
            $where_cats = '1 = 1';
        } elseif (!is_array($ids)) {
            $where_cats = '%s=' . $ids;
        } else {
            if (count($ids) === 0) {
                return false;
            }
            $where_cats = '%s ' . $this->em->getConnection()->in($ids);
        }

        // find all categories where the setted representative is not possible : the picture does not exist
        $result = $this->em->getRepository(CategoryRepository::class)->findWrongRepresentant($where_cats);
        $wrong_representant = $this->em->getConnection()->result2array($result, null, 'id');

        if (count($wrong_representant) > 0) {
            $this->albumRepository->updateAlbums(['representative_picture_id' => null], $wrong_representant);
        }

        if (!$this->conf['allow_random_representative']) {
            // If the random representant is not allowed, we need to find
            // categories with elements and with no representant. Those categories
            // must be added to the list of categories to set to a random
            // representant.
            $result = $this->em->getRepository(CategoryRepository::class)->findRandomRepresentant($where_cats);
            $to_rand = $this->em->getConnection()->result2array($result, null, 'id');
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
        if (count($ids) == 0) {
            return;
        }

        // add sub-category ids to the given ids : if a category is deleted, all
        // sub-categories must be so
        $ids = array_merge($ids, $this->em->getRepository(CategoryRepository::class)->getSubcatIds($ids));

        // destruction of the links between images and this category
        $this->em->getRepository(ImageCategoryRepository::class)->deleteByCategory($ids);

        // destruction of the access linked to the category
        $this->em->getRepository(UserAccessRepository::class)->deleteByCatIds($ids);
        $this->em->getRepository(GroupAccessRepository::class)->deleteByCatIds($ids);

        // destruction of the category
        $this->em->getRepository(CategoryRepository::class)->deleteByIds($ids);

        $this->em->getRepository(OldPermalinkRepository::class)->deleteByCatIds($ids);
        $this->em->getRepository(UserCacheCategoriesRepository::class)->deleteByUserCatIds($ids);
    }

    /**
     * Associate a list of images to a list of albums.
     * The function will not duplicate links and will preserve ranks.
     */
    public function associateImagesToAlbums(array $images, array $categories)
    {
        if (count($images) === 0 || count($categories) === 0) {
            return false;
        }

        // get existing associations
        $result = $this->em->getRepository(ImageCategoryRepository::class)->findAll($images, $categories);

        $existing = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $existing[$row['category_id']][] = $row['image_id'];
        }

        // get max rank of each categories
        $result = $this->em->getRepository(ImageCategoryRepository::class)->findMaxRankForEachCategories($categories);
        $current_rank_of = $this->em->getConnection()->result2array($result, 'category_id', 'max_rank');

        // associate only not already associated images
        $inserts = [];
        foreach ($categories as $category_id) {
            if (!isset($current_rank_of[$category_id])) {
                $current_rank_of[$category_id] = 0;
            }
            if (!isset($existing[$category_id])) {
                $existing[$category_id] = [];
            }

            foreach ($images as $image_id) {
                if (!in_array($image_id, $existing[$category_id])) {
                    $rank = ++$current_rank_of[$category_id];

                    $inserts[] = [
                        'image_id' => $image_id,
                        'category_id' => $category_id,
                        'rank' => $rank,
                    ];
                }
            }
        }

        if (count($inserts)) {
            $this->em->getRepository(ImageCategoryRepository::class)->insertImageCategories(
                array_keys($inserts[0]),
                $inserts
            );

            $this->updateAlbum($categories);
        }
    }

    /**
     * Dissociate images from all old albums except their storage album and
     * associate to new albums.
     * This function will preserve ranks.
     */
    public function moveImagesToAlbums(array $images, array $categories)
    {
        if (count($images) === 0) {
            return false;
        }

        $result = $this->em->getRepository(ImageRepository::class)->findWithNoStorageOrStorageCategoryId($categories);
        $cat_ids = $this->em->getConnection()->result2array($result, null, 'id');

        // let's first break links with all old albums but their "storage album"
        $this->em->getRepository(ImageCategoryRepository::class)->deleteByCategory($cat_ids, $images);

        if (is_array($categories) && count($categories) > 0) {
            $this->associateImagesToAlbums($images, $categories);
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

        $datas = [];
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

            $datas[] = ['id' => $id, 'rank' => $current_rank];
        }
        $fields = ['primary' => ['id'], 'update' => ['rank']];
        $this->em->getRepository(CategoryRepository::class)->massUpdatesCategories($fields, $datas);

        $this->updateGlobalRank();
    }

    // temporary because GroupAccess is not managed by doctrine

    /**
     * Same as displaySelectCategories but categories are ordered by rank
     * @see displaySelectCategories()
     */
    public function displaySelectCategoriesWrapper(array $categories, array $selecteds, string $blockname, bool $fullname = true): array
    {
        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

        return $this->displaySelectCategories($categories, $selecteds, $blockname, $fullname);
    }

    /**
     * @param array[] $categories (at least id,name,global_rank,uppercats for each)
     * @param int[] $selected ids of selected items
     * @param string $blockname variable name in template
     * @param bool $fullname full breadcrumb or not
     */
    public function displaySelectCategories(array $categories, array $selecteds, string $blockname, bool $fullname = true)
    {
        $tpl_cats = [];
        foreach ($categories as $category) {
            if ($fullname) {
                $option = strip_tags($this->getCatDisplayNameCache($category['uppercats']));
            } else {
                $option = str_repeat('&nbsp;', (3 * substr_count($category['global_rank'], '.')));
                $option .= '- ';
                $option .= strip_tags($category['name']);
            }
            $tpl_cats[$category['id']] = $option;
        }

        return [
            $blockname => $tpl_cats,
            $blockname . '_selected' => $selecteds
        ];
    }

    /**
     * Generates breadcrumb from categories list using a cache.
     * @see getCatDisplayName()
     */
    public function getCatDisplayNameCache(string $uppercats, string $url = '', bool $single_link = false, string $link_class = ''): string
    {
        $cache = [];

        if (!isset($cache['cat_names'])) {
            $result = $this->em->getRepository(CategoryRepository::class)->findAll();
            $cache['cat_names'] = $this->em->getConnection()->result2array($result, 'id');
        }

        $output = '';
        if ($single_link) {
            $single_url = $this->router->generate('album', ['category_id' => array_pop(explode(',', $uppercats)) ]);
            $output .= '<a href="' . $single_url . '"';
            if (isset($link_class)) {
                $output .= ' class="' . $link_class . '"';
            }
            $output .= '>';
        }

        // @TODO: refactoring with getCatDisplayName
        $is_first = true;
        foreach (explode(',', $uppercats) as $category_id) {
            $cat = $cache['cat_names'][$category_id];

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $this->conf['level_separator'];
            }

            if (!isset($url) || $single_link) {
                $output .= $cat['name'];
            } else {
                $output .= '<a href="' . $this->router->generate('album', ['category_id' => $cat['id']]) . '">' . $cat['name'] . '</a>';
            }
        }

        if ($single_link && isset($single_url)) {
            $output .= '</a>';
        }

        return $output;
    }

    public function getUppernamesBreadcrumb(array $upper_names = []): array
    {
        $breadcumb = [];

        foreach ($upper_names as $category) {
            $breadcumb[] = [
                'url' => $this->router->generate('album', ['category_id' => $category['id']]),
                'label' => $category['name']
            ];
        }

        return $breadcumb;
    }
}
