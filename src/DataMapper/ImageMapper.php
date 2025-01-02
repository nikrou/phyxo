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

use App\Enum\ImageSizeType;
use App\Enum\PictureSectionType;
use Exception;
use App\Repository\CaddieRepository;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use App\Repository\CommentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\HistoryRepository;
use App\Repository\ImageAlbumRepository;
use App\Repository\ImageTagRepository;
use App\Repository\ImageRepository;
use App\Repository\RateRepository;
use App\Services\DerivativeService;
use DateTimeInterface;
use Phyxo\Functions\Utils;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImageMapper
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly UserMapper $userMapper,
        private Conf $conf,
        private readonly ImageStandardParams $image_std_params,
        private readonly AlbumMapper $albumMapper,
        private readonly HistoryRepository $historyRepository,
        private readonly TranslatorInterface $translator,
        private readonly ImageRepository $imageRepository,
        private readonly ImageAlbumRepository $imageAlbumRepository,
        private readonly CommentRepository $commentRepository,
        private readonly CaddieRepository $caddieRepository,
        private readonly FavoriteRepository $favoriteRepository,
        private readonly RateRepository $rateRepository,
        private readonly ImageTagRepository $imageTagRepository,
        private readonly DerivativeService $derivativeService
    ) {
    }

    public function getRepository(): ImageRepository
    {
        return $this->imageRepository;
    }

    /**
     * @param int|string $element_id
     * @param array{current_day?: DateTimeInterface, date_type?: string, year?: int, month?: int, day?: int } $extra
     * @param int[] $selection
     *
     * @return array<string, mixed>
     */
    public function getPicturesFromSelection($element_id, PictureSectionType $section, array $selection = [], int $start_id = 0, array $extra = []): array
    {
        $tpl_params = [];

        if ($selection === []) {
            return [];
        }

        $rank_of = array_flip($selection);
        $pictures = [];

        foreach ($this->imageRepository->findBy(['id' => $selection]) as $image) {
            $image_infos = $image->toArray();
            $image_infos['image'] = $image;
            $image_infos['rank'] = $rank_of[$image->getId()];
            $pictures[] = $image_infos;
        }

        usort($pictures, Utils::rankCompare(...));
        unset($rank_of);

        if ($pictures !== [] && ($this->conf['activate_comments'] && $this->userMapper->getUser()->getUserInfos()->getShowNbComments())) {
            $nb_comments_of = [];
            foreach ($this->commentRepository->countGroupByImage($selection) as $comment) {
                $nb_comments_of[$comment['image_id']] = $comment['nb_comments'];
            }
        }

        foreach ($pictures as $picture) {
            if (in_array($section, [PictureSectionType::ALBUM, PictureSectionType::LIST, PictureSectionType::TAGS, PictureSectionType::SEARCH])) {
                $url = $this->router->generate(
                    'picture',
                    [
                        'image_id' => $picture['image']->getId(),
                        'section' => $section->value,
                        'element_id' => $element_id,
                    ]
                );
            } elseif ($section === PictureSectionType::CALENDAR_ALBUMS) {
                $url = $this->router->generate(
                    'picture_categories_from_calendar',
                    [
                        'image_id' => $picture['image']->getId(),
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                        'extra' => 'extr',
                    ]
                );
            } elseif ($section === PictureSectionType::FROM_CALENDAR) {
                $url = $this->router->generate(
                    'picture_from_calendar',
                    [
                        'image_id' => $picture['image']->getId(),
                        'date_type' => $extra['date_type'],
                        'year' => $extra['year'], 'month' => sprintf('%02d', $extra['month']), 'day' => sprintf('%02d', $extra['day']),
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : '',
                    ]
                );
            } else {
                $url = $this->router->generate(
                    'picture_by_type',
                    [
                        'image_id' => $picture['image']->getId(),
                        'section' => $section->value,
                        'start_id' => $start_id !== 0 ? 'start-' . $start_id : ''
                    ]
                );
            }

            if (isset($nb_comments_of, $nb_comments_of[$picture['image']->getId()])) {
                $picture['NB_COMMENTS'] = $picture['nb_comments'] = $nb_comments_of[$picture['image']->getId()];
            }

            $name = Utils::renderElementName($picture);
            $desc = Utils::renderElementDescription($picture, 'main_page_element_description');

            $tpl_var = array_merge($picture, [
                'TN_ALT' => htmlspecialchars(strip_tags($name)),
                'TN_TITLE' => $this->getThumbnailTitle($picture, $name, $desc),
                'URL' => $url,
                'DESCRIPTION' => $desc,
                'icon_ts' => '',
            ]);

            if ($this->conf['index_new_icon']) {
                //$tpl_var['icon_ts'] = $this->em->getRepository(BaseRepository::class)->getIcon($row['date_available'], $this->userMapper->getUser());
            }

            if ($this->userMapper->getUser()->getUserInfos()->getShowNbHits()) {
                $tpl_var['NB_HITS'] = $picture['image']->getHit();
            }

            if ($section === PictureSectionType::BEST_RATED) {
                $name = '(' . $picture['image']->getRatingScore() . ') ' . $name;
            } elseif ($section === PictureSectionType::MOST_VISITED) {
                if (!$this->userMapper->getUser()->getUserInfos()->getShowNbHits()) {
                    $name = '(' . $picture['image']->getHit() . ') ' . $name;
                }
            }

            $tpl_var['NAME'] = $name;
            $tpl_params['thumbnails'][] = $tpl_var;
        }

        // @TODO: retrieve index_deriv in another_way
        $tpl_params['derivative_params'] = $this->image_std_params->getByType($_SESSION['index_deriv'] ?? ImageSizeType::THUMB);

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
     * @return int number of deleted elements
     */
    public function deleteElements(array $ids, bool $physical_deletion = false): int
    {
        if (count($ids) == 0) {
            return 0;
        }

        if ($physical_deletion) {
            $ids = $this->deleteElementFiles($ids);
            if ((is_countable($ids) ? count($ids) : 0) == 0) {
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

        // destruction of the history associated to this element
        $this->historyRepository->deleteElements($ids);

        // destruction of the image
        $this->imageRepository->deleteByIds($ids);

        // are the photo used as album representant?
        $album_ids = [];
        foreach ($this->albumMapper->getRepository()->findRepresentants($ids) as $album) {
            $album_ids[] = $album->getId();
        }
        if ($album_ids !== []) {
            $this->albumMapper->updateAlbums($album_ids);
        }

        return is_countable($ids) ? count($ids) : 0;
    }

    /**
     * Deletes all files (on disk) related to given image ids.
     *
     * @param int[] $ids
     * @return 0|int[] image ids where files were successfully deleted
     */
    public function deleteElementFiles(array $ids = []): int|array
    {
        if (count($ids) == 0) {
            return 0;
        }

        $new_ids = [];
        foreach ($this->imageRepository->findBy(['id' => $ids]) as $image) {
            $files = [];
            $files[] = $image->getPath();

            $fs = new Filesystem();
            $ok = true;
            if (!isset($this->conf['never_delete_originals'])) {
                foreach ($files as $path) {
                    try {
                        $fs->remove($path);
                    } catch (Exception) {
                        $ok = false; //trigger_error('"' . $path . '" cannot be removed', E_USER_WARNING);
                    }
                }
            }

            if ($ok) {
                $this->derivativeService->deleteForElement($image->getPath());
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
     * @param array<string, mixed> $info
     */
    public function getThumbnailTitle(array $info, string $title, ?string $comment = ''): string
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

        if ($details !== []) {
            $title .= ' (' . implode(', ', $details) . ')';
        }

        if ($comment !== null && $comment !== '' && $comment !== '0') {
            $comment = strip_tags($comment);
            $title .= ' ' . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '');
        }

        return htmlspecialchars(strip_tags($title));
    }
}
