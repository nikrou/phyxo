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

use App\Repository\CaddieRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\EntityManager;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CommentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageTagRepository;
use App\Repository\RateRepository;
use Phyxo\Functions\Plugin;
use Phyxo\Functions\Utils;
use Phyxo\Image\SrcImage;
use Symfony\Component\Routing\RouterInterface;
use Phyxo\Functions\Language;
use Symfony\Component\Filesystem\Filesystem;

class ImageMapper
{
    private $em, $router, $conf, $userMapper, $image_std_params, $categoryMapper;
    private $sql_recent_date;

    public function __construct(EntityManager $em, RouterInterface $router, UserMapper $userMapper, Conf $conf, ImageStandardParams $image_std_params, CategoryMapper $categoryMapper)
    {
        $this->em = $em;
        $this->router = $router;
        $this->userMapper = $userMapper;
        $this->conf = $conf;
        $this->image_std_params = $image_std_params;
        $this->categoryMapper = $categoryMapper;
    }

    public function getPicturesFromSelection(array $selection = [], $element_id, string $section = '', int $start_id = 0): array
    {
        $tpl_params = [];

        if (count($selection) === 0) {
            return [];
        }

        $rank_of = array_flip($selection);

        $result = $this->em->getRepository(ImageRepository::class)->findByIds($selection);
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $row['rank'] = $rank_of[$row['id']];
            $pictures[] = $row;
        }

        usort($pictures, '\Phyxo\Functions\Utils::rank_compare');
        unset($rank_of);

        // temporary fix
        if ($section === 'categories') {
            $section = 'category';
        }

        if (count($pictures) > 0) {
            // define category slideshow url
            $row = reset($pictures);
            if (in_array($section, ['category', 'list', 'tags', 'search'])) {
                $tpl_params['cat_slideshow_url'] = $this->router->generate(
                    'picture',
                    [
                        'image_id' => $row['id'],
                        'type' => $section,
                        'element_id' => $element_id,
                        'slideshow' => (isset($_GET['slideshow']) ? $_GET['slideshow'] : '')
                    ]
                );
            } elseif (in_array($section, ['calendar_categories'])) {
                $tpl_params['cat_slideshow_url'] = $this->router->generate(
                    'picture_categories_from_calendar',
                    [
                        'image_id' => $row['id'],
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'extra' => 'ext',
                        'slideshow' => (isset($_GET['slideshow']) ? $_GET['slideshow'] : '')
                    ]
                );
            } elseif (in_array($section, ['calendar_category'])) {
                $url = $this->router->generate(
                    'picture_categories_from_calendar',
                    [
                        'image_id' => $row['id'],
                        'category_id' => $element_id,
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'extra' => 'extr',
                    ]
                );
            } else {
                $tpl_params['cat_slideshow_url'] = $this->router->generate(
                    'picture_by_type',
                    [
                        'image_id' => $row['id'],
                        'type' => $section,
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'slideshow' => (isset($_GET['slideshow']) ? $_GET['slideshow'] : '')
                    ]
                );
            }
            if ($this->conf['activate_comments'] && $this->userMapper->getUser()->getShowNbComments()) {
                $result = $this->em->getRepository(CommentRepository::class)->countGroupByImage($selection);
                $nb_comments_of = $this->em->getConnection()->result2array($result, 'image_id', 'nb_comments');
            }
        }

        Plugin::trigger_notify('loc_begin_index_thumbnails', $pictures);

        foreach ($pictures as $row) {
            if (in_array($section, ['category', 'list', 'tags', 'search'])) {
                $url = $this->router->generate(
                    'picture',
                    [
                        'image_id' => $row['id'],
                        'type' => $section,
                        'element_id' => $element_id,
                    ]
                );
            } elseif (in_array($section, ['calendar_categories'])) {
                $url = $this->router->generate(
                    'picture_categories_from_calendar',
                    [
                        'image_id' => $row['id'],
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'extra' => 'extr',
                    ]
                );
            } elseif (in_array($section, ['calendar_category'])) {
                $url = $this->router->generate(
                    'picture_categories_from_calendar',
                    [
                        'image_id' => $row['id'],
                        'category_id' => $element_id,
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'extra' => 'extr',
                    ]
                );
            } else {
                $url = $this->router->generate(
                    'picture_by_type',
                    [
                        'image_id' => $row['id'],
                        'type' => $section,
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : ''
                    ]
                );
            }

            if (isset($nb_comments_of, $nb_comments_of[$row['id']])) {
                $row['NB_COMMENTS'] = $row['nb_comments'] = (int) $nb_comments_of[$row['id']];
            }

            $name = Utils::render_element_name($row);
            $desc = Utils::render_element_description($row, 'main_page_element_description');

            $tpl_var = array_merge($row, [
                'TN_ALT' => htmlspecialchars(strip_tags($name)),
                'TN_TITLE' => Utils::get_thumbnail_title($row, $name, $desc),
                'URL' => $url,
                'DESCRIPTION' => $desc,
                'src_image' => new SrcImage($row, $this->conf['picture_ext']),
            ]);

            if ($this->conf['index_new_icon']) {
                $tpl_var['icon_ts'] = $this->getIcon($row['date_available']);
            }

            if ($this->userMapper->getUser()->getShowNbHits()) {
                $tpl_var['NB_HITS'] = $row['hit'];
            }

            if ($section === 'best_rated') {
                $name = '(' . $row['rating_score'] . ') ' . $name;
            } elseif ($section === 'most_visited') {
                if (!$this->userMapper->getUser()->getShowNbHits()) {
                    $name = '(' . $row['hit'] . ') ' . $name;
                }
            }

            $tpl_var['NAME'] = $name;
            $tpl_params['thumbnails'][] = $tpl_var;
        }

        $tpl_params['derivative_params'] = Plugin::trigger_change(
            'get_index_derivative_params',
            $this->image_std_params->getByType(isset($_SESSION['index_deriv']) ? $_SESSION['index_deriv'] : ImageStandardParams::IMG_THUMB) // @TODO: retrieve index_deriv in another_way
        );

        return $tpl_params;
    }

    public function getIcon(string $date, bool $is_child_date = false): array
    {
        if (empty($date)) {
            return [];
        }

        if (empty($this->sql_recent_date)) {
            $this->sql_recent_date = $this->em->getConnection()->db_get_recent_period($this->userMapper->getUser()->getRecentPeriod());
        }

        $icon = [
            'TITLE' => Language::l10n('photos posted during the last %d days', $this->userMapper->getUser()->getRecentPeriod()),
            'IS_CHILD_DATE' => $is_child_date,
            'sql_recent_date' => $this->sql_recent_date
        ];

        return ($date > $icon['sql_recent_date']) ? $icon : [];
    }

    /**
     * Deletes elements from database.
     * It also deletes :
     *    - all the comments related to elements
     *    - all the links between categories/tags and elements
     *    - all the favorites/rates associated to elements
     *    - removes elements from caddie
     *
     * @param int[] $ids
     * @param bool $physical_deletion
     * @return int number of deleted elements
     */
    public function deleteElements(array $ids, bool $physical_deletion = false)
    {
        if (count($ids) == 0) {
            return 0;
        }

        \Phyxo\Functions\Plugin::trigger_notify('begin_delete_elements', $ids);

        if ($physical_deletion) {
            $ids = $this->deleteElementFiles($ids);
            if (count($ids) == 0) {
                return 0;
            }
        }

        // destruction of the comments on the image
        $this->em->getRepository(CommentRepository::class)->deleteByImage($ids);

        // destruction of the links between images and categories
        $this->em->getRepository(ImageCategoryRepository::class)->deleteBy('image_id', $ids);

        // destruction of the links between images and tags
        $this->em->getRepository(ImageTagRepository::class)->deleteBy('image_id', $ids);

        // destruction of the favorites associated with the picture
        $this->em->getRepository(FavoriteRepository::class)->deleteImagesFromFavorite($ids);

        // destruction of the rates associated to this element
        $this->em->getRepository(RateRepository::class)->deleteByElementIds($ids);

        // destruction of the caddie associated to this element
        $this->em->getRepository(CaddieRepository::class)->deleteElements($ids);

        // destruction of the image
        $this->em->getRepository(ImageRepository::class)->deleteByElementIds($ids);

        // are the photo used as category representant?
        $result = $this->em->getRepository(CategoryRepository::class)->findRepresentants($ids);
        $category_ids = $this->em->getConnection()->result2array($result, null, 'id');
        if (count($category_ids) > 0) {
            $this->categoryMapper->updateCategory($category_ids);
        }

        return count($ids);
    }

    /**
     * Deletes all files (on disk) related to given image ids.
     *
     * @param int[] $ids
     * @return 0|int[] image ids where files were successfully deleted
     */
    public function deleteElementFiles(array $ids = [])
    {
        if (count($ids) == 0) {
            return 0;
        }

        $new_ids = [];
        $result = $this->em->getRepository(ImageRepository::class)->findByIds($ids);
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            if (\Phyxo\Functions\URL::url_is_remote($row['path'])) {
                continue;
            }

            $files = [];
            $files[] = \Phyxo\Functions\Utils::get_element_path($row);

            if (!empty($row['representative_ext'])) {
                $files[] = \Phyxo\Functions\Utils::original_to_representative($files[0], $row['representative_ext']);
            }

            $fs = new Filesystem();
            $ok = true;
            if (!isset($this->conf['never_delete_originals'])) {
                foreach ($files as $path) {
                    try {
                        $fs->remove($path);
                    } catch (\Exception $e) {
                        $ok = false; //trigger_error('"' . $path . '" cannot be removed', E_USER_WARNING);
                    }
                }
            }

            if ($ok) {
                Utils::delete_element_derivatives($row);
                $new_ids[] = $row['id'];
            } else {
                break;
            }
        }

        return $new_ids;
    }
}
