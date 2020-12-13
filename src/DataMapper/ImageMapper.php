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
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CommentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageRepository;
use App\Repository\RateRepository;
use Phyxo\Functions\URL;
use Phyxo\Functions\Utils;
use Phyxo\Image\SrcImage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImageMapper
{
    private $router, $conf, $userMapper, $image_std_params, $albumMapper, $imageRepository, $imageTagRepository;
    private $translator, $imageAlbumRepository, $commentRepository, $caddieRepository, $favoriteRepository, $rateRepository;

    public function __construct(RouterInterface $router, UserMapper $userMapper, Conf $conf, ImageStandardParams $image_std_params, AlbumMapper $albumMapper,
                                TranslatorInterface $translator, ImageRepository $imageRepository, ImageAlbumRepository $imageAlbumRepository, CommentRepository $commentRepository,
                                CaddieRepository $caddieRepository, FavoriteRepository $favoriteRepository, RateRepository $rateRepository, ImageTagRepository $imageTagRepository)
    {
        $this->router = $router;
        $this->userMapper = $userMapper;
        $this->conf = $conf;
        $this->image_std_params = $image_std_params;
        $this->albumMapper = $albumMapper;
        $this->translator = $translator;
        $this->imageRepository = $imageRepository;
        $this->imageTagRepository = $imageTagRepository;
        $this->imageAlbumRepository = $imageAlbumRepository;
        $this->commentRepository = $commentRepository;
        $this->caddieRepository = $caddieRepository;
        $this->favoriteRepository = $favoriteRepository;
        $this->rateRepository = $rateRepository;
    }

    public function getRepository(): ImageRepository
    {
        return $this->imageRepository;
    }

    public function getPicturesFromSelection(array $selection = [], $element_id, string $section = '', int $start_id = 0): array
    {
        $tpl_params = [];

        if (count($selection) === 0) {
            return [];
        }

        $rank_of = array_flip($selection);

        foreach ($this->imageRepository->findBy(['id' => $selection]) as $image) {
            $image_infos = $image->toArray();
            $image_infos['rank'] = $rank_of[$image->getId()];
            $pictures[] = $image_infos;
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
                $nb_comments_of = [];
                foreach ($this->commentRepository->countGroupByImage($selection) as $comment) {
                    $nb_comments_of[$comment['image_id']] = $comment['nb_comments'];
                }
            }
        }

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
                'TN_TITLE' => $this->getThumbnailTitle($row, $name, $desc),
                'URL' => $url,
                'DESCRIPTION' => $desc,
                'src_image' => new SrcImage($row, $this->conf['picture_ext']),
            ]);

            if ($this->conf['index_new_icon']) {
                //$tpl_var['icon_ts'] = $this->em->getRepository(BaseRepository::class)->getIcon($row['date_available'], $this->userMapper->getUser());
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

        // @TODO: retrieve index_deriv in another_way
        $tpl_params['derivative_params'] = $this->image_std_params->getByType(isset($_SESSION['index_deriv']) ? $_SESSION['index_deriv'] : ImageStandardParams::IMG_THUMB);

        return $tpl_params;
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

        if ($physical_deletion) {
            $ids = $this->deleteElementFiles($ids);
            if (count($ids) == 0) {
                return 0;
            }
        }

        // destruction of the comments on the image
        $this->commentRepository->deleteByImage($ids);

        // destruction of the links between images and categories
        $this->imageAlbumRepository->deleteByImages($ids);

        // destruction of the links between images and tags
        $this->imageTagRepository->deleteByImageIds($ids);

        // destruction of the favorites associated with the picture
        $this->favoriteRepository->deleteImagesFromFavorite($ids);

        // destruction of the rates associated to this element
        $this->rateRepository->deleteByImageIds($ids);

        // destruction of the caddie associated to this element
        $this->caddieRepository->deleteElements($ids);

        // destruction of the image
        $this->imageRepository->deleteByIds($ids);

        // are the photo used as album representant?
        $album_ids = [];
        foreach ($this->albumMapper->getRepository()->findRepresentants($ids) as $album) {
            $album_ids[] = $album->getId();
        }
        if (count($album_ids) > 0) {
            $this->albumMapper->updateAlbums($album_ids);
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
        foreach ($this->imageRepository->findBy(['id' => $ids]) as $image) {
            if (URL::url_is_remote($image->getPath())) {
                continue;
            }

            $files = [];
            $files[] = Utils::get_element_path($image->toArray());

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
                Utils::delete_element_derivatives($image->toArray());
                $new_ids[] = $image->getId();
            } else {
                break;
            }
        }

        return $new_ids;
    }

    /**
     * Add info to the title of the thumbnail based on photo properties.
     *
     * @param array $info hit, rating_score, nb_comments
     * @param string $title
     * @param string $comment
     * @return string
     */
    public function getThumbnailTitle($info, $title, $comment = ''): string
    {
        $details = [];

        if (!empty($info['hit'])) {
            $details[] = $info['hit'] . ' ' . strtolower($this->translator->trans('Visits'));
        }

        if ($this->conf['rate'] && !empty($info['rating_score'])) {
            $details[] = strtolower($this->translator->trans('Rating score')) . ' ' . $info['rating_score'];
        }

        if (isset($info['nb_comments']) && $info['nb_comments'] != 0) {
            $details[] = $this->translator->trans('number_of_comments', ['count' => $info['nb_comments']]);
        }

        if (count($details) > 0) {
            $title .= ' (' . implode(', ', $details) . ')';
        }

        if (!empty($comment)) {
            $comment = strip_tags($comment);
            $title .= ' ' . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '');
        }

        $title = htmlspecialchars(strip_tags($title));

        return $title;
    }
}
