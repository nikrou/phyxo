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

namespace App\Controller\Admin;

use App\DataMapper\AlbumMapper;
use App\DataMapper\ImageMapper;
use App\DataMapper\UserMapper;
use App\Entity\History;
use App\Entity\HistorySummary;
use App\Entity\Search;
use App\Form\HistorySearchType;
use App\Form\Model\SearchRulesModel;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\SearchRepository;
use App\Repository\TagRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminHistoryController extends AbstractController
{
    public function __construct(private readonly ImageStandardParams $image_std_params, private readonly TranslatorInterface $translator)
    {
    }

    protected function setTabsheet(string $section = 'stats'): TabSheet
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('stats', $this->translator->trans('Statistics', [], 'admin'), $this->generateUrl('admin_history'), 'fa-signal');
        $tabsheet->add('search', $this->translator->trans('Search', [], 'admin'), $this->generateUrl('admin_history_search'), 'fa-search');
        $tabsheet->select($section);

        return $tabsheet;
    }

    public function stats(
        Request $request,
        Conf $conf,
        HistorySummaryRepository $historySummaryRepository,
        HistoryRepository $historyRepository,
        int $year = null,
        int $month = null,
        int $day = null
    ): Response {
        $tpl_params = [];
        $this->refreshSummary($historyRepository, $historySummaryRepository);

        $title_parts = [];
        $title_parts[] = '<a href="' . $this->generateUrl('admin_history') . '">' . $this->translator->trans('Overall', [], 'admin') . '</a>';

        $period_label = $this->translator->trans('Year', [], 'admin');
        if (!is_null($year)) {
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year', ['year' => $year]) . '">' . $year . '</a>';
            $period_label = $this->translator->trans('Month', [], 'admin');
        }

        if (!is_null($month)) {
            $month_title = $this->dateFormat($request->get('_locale'), mktime(12, 0, 0, $month, 1, $year), 'LLLL');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month', ['year' => $year, 'month' => sprintf('%02d', $month)]) . '">' . $month_title . '</a>';
            $period_label = $this->translator->trans('Day', [], 'admin');
        }

        if (!is_null($day)) {
            $day_title = $this->dateFormat($request->get('_locale'), mktime(12, 0, 0, $month, $day, $year), 'd (cccc)');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month_day', ['year' => $year, 'month' => sprintf('%02d', $month), 'day' => sprintf('%02d', $day)]) . '">' . $day_title . '</a>';
            $period_label = $this->translator->trans('Hour', [], 'admin');
        }

        $tpl_params['L_STAT_TITLE'] = implode($conf['level_separator'], $title_parts);
        $tpl_params['PERIOD_LABEL'] = $period_label;

        $datas = [];
        if (!is_null($day)) {
            $method = 'getHour';
            $min_x = 0;
            $max_x = 23;
        } elseif (!is_null($month)) {
            $method = 'getDay';
            $min_x = 1;
            $max_x = date('t', mktime(12, 0, 0, $month, 1, $year));
        } elseif (!is_null($year)) {
            $method = 'getMonth';
            $min_x = 1;
            $max_x = 12;
        } else {
            $method = 'getYear';
        }

        $max_pages = 1;
        foreach ($historySummaryRepository->getSummary($year, $month, $day) as $historySummary) {
            if ($historySummary->getNbPages() > $max_pages) {
                $max_pages = $historySummary->getNbPages();
            }

            $datas[$historySummary->$method()] = $historySummary->getNbPages();
        }

        $numberOfPages = array_sum($datas);

        $min_x = min(array_keys($datas));
        $max_x = max(array_keys($datas));

        if (count($datas) > 0) {
            for ($i = $min_x; $i <= $max_x; $i++) {
                if (!isset($datas[$i])) {
                    $datas[$i] = 0;
                }

                $url = null;

                if (!is_null($day)) {
                    $value = sprintf('%02u', $i);
                } elseif (!is_null($month)) {
                    $url = $this->generateUrl('admin_history_year_month_day', ['year' => $year, 'month' => sprintf('%02d', $month), 'day' => sprintf('%02d', $i)]);
                    $value = $this->dateFormat($request->get('_locale'), mktime(12, 0, 0, $month, $i, $year), 'd (cccc)');
                } elseif (!is_null($year)) {
                    $url = $this->generateUrl('admin_history_year_month', ['year' => $year, 'month' => sprintf('%02d', $i)]);
                    $value = $this->dateFormat($request->get('_locale'), mktime(12, 0, 0, $i, 1, $year), 'LLLL');
                } else { // at least the year is defined
                    $url = $this->generateUrl('admin_history_year', ['year' => $i]);
                    $value = $i;
                }

                if ($datas[$i] !== 0 && isset($url)) {
                    $value = '<a href="' . $url . '">' . $value . '</a>';
                }

                $tpl_params['statrows'][] = [
                    'VALUE' => $value,
                    'PAGES' => $datas[$i],
                    'WIDTH' => round($datas[$i] / $numberOfPages * 100)
                ];
            }
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_history');
        $tpl_params['tabsheet'] = $this->setTabsheet('stats');

        return $this->render('history_stats.html.twig', $tpl_params);
    }

    public function search(
        Request $request,
        SearchRepository $searchRepository,
        int $start,
        AlbumMapper $albumMapper,
        Conf $conf,
        UserMapper $userMapper,
        ImageMapper $imageMapper,
        TagRepository $tagRepository,
        HistoryRepository $historyRepository,
        RouterInterface $router,
        int $search_id = null
    ): Response {
        $tpl_params = [];
        $search = null;
        $rules = null;

        if (!is_null($search_id)) {
            $search = $searchRepository->findOneBy(['id' => $search_id]);
            if (!is_null($search) && !empty($search->getRules())) {
                $rules = unserialize(base64_decode($search->getRules()));
            }

            $results = $this->getElementFromSearchRules(
                $rules,
                $start,
                $conf,
                $historyRepository,
                $albumMapper,
                $userMapper,
                $imageMapper,
                $tagRepository
            );
            $tpl_params['search_summary'] = $results['search_summary'];
            $tpl_params['search_results'] = $results['search_results'];
            $tpl_params['nb_lines'] = $results['nb_lines'];

            if ($results['nb_lines'] > $conf['nb_logs_page']) {
                $tpl_params['navbar'] = Utils::createNavigationBar(
                    $router,
                    'admin_history_search',
                    ['search_id' => $search_id],
                    $results['nb_lines'],
                    $start,
                    $conf['nb_logs_page']
                );
            }
        }

        $historySearchForm = $this->createForm(HistorySearchType::class, $rules, ['translation_domain' => 'admin']);
        $historySearchForm->handleRequest($request);

        if ($historySearchForm->isSubmitted() && $historySearchForm->isValid()) {
            $rules = $historySearchForm->getData();

            $search = new Search();
            $search->setRules(base64_encode(serialize($rules)));
            $searchRepository->addSearch($search);

            $cookie = Cookie::create('display_thumbnail', $rules->getDisplayThumbnail(), strtotime('+1 month'), $request->getBasePath());
            $response = new RedirectResponse($this->generateUrl('admin_history_search', ['search_id' => $search->getId()]));
            $response->headers->setCookie($cookie);

            return $response;
        }

        $tpl_params['history_search_form'] = $historySearchForm->createView();

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_history');
        $tpl_params['PAGE_TITLE'] = $this->translator->trans('History', [], 'admin');
        $tpl_params['tabsheet'] = $this->setTabsheet('search');

        return $this->render('history_search.html.twig', $tpl_params);
    }

    /** @phpstan-ignore-next-line */ // @FIX: define return type
    protected function getElementFromSearchRules(
        SearchRulesModel $rules,
        int $start,
        Conf $conf,
        HistoryRepository $historyRepository,
        AlbumMapper $albumMapper,
        UserMapper $userMapper,
        ImageMapper $imageMapper,
        TagRepository $tagRepository
    ): array {
        $search_results = [];

        if ($rules->getFilename()) {
            foreach ($imageMapper->getRepository()->findBy(['file' => $rules->getFilename()]) as $image) {
                $rules->addImageId($image->getId());
            }
        }

        $nb_lines = $historyRepository->getHistory($rules, HistorySearchType::TYPES, 0, 0, $count_only = true);

        $history_lines = [];
        $user_ids = [];
        $username_of = [];
        $category_ids = [];
        $image_ids = [];
        $image_infos = [];
        $has_tags = false;

        foreach ($historyRepository->getHistory($rules, HistorySearchType::TYPES, $conf['nb_logs_page'], $start) as $history) {
            $user_ids[] = $history->getUser()->getId();
            $username_of[$history->getUser()->getId()] = $history->getUser()->getUserIdentifier();

            if ($history->getAlbum()) {
                $category_ids[] = $history->getAlbum()->getId();
                $uppercats_of[$history->getAlbum()->getId()] = $history->getAlbum()->getUppercats();
                $name_of_category[$history->getAlbum()->getId()] = $albumMapper->getAlbumsDisplayNameCache($history->getAlbum()->getUppercats());
            }

            if ($history->getImage()) {
                $image_ids[] = $history->getImage()->getId();
                $image_infos[$history->getImage()->getId()] = $history->getImage();
            }

            if ($history->getTagIds()) {
                $has_tags = true;
            }

            $history_lines[] = $history;
        }

        $name_of_tag = [];
        if ($has_tags > 0) {
            foreach ($tagRepository->findAll() as $tag) {
                $name_of_tag[$tag->getId()] = [
                    'name' => $tag->getName(),
                    'url' => '<a href="' . $this->generateUrl('images_by_tags', ['tag_ids' => $tag->toUrl()]) . '">' . $tag->getName() . '</a>',
                ];
            }
        }

        $summary = [];
        $summary['total_filesize'] = 0;
        $summary['guests_ip'] = [];

        $guest_id = $userMapper->getDefaultUser()->getId();

        foreach ($history_lines as $line) {
            if ($line->getImageType() && $line->getImageType() === 'high') {
                if (isset($image_infos[$line->getImage()->getId()]['filesize'])) {
                    $summary['total_filesize'] += $image_infos[$line->getImage()->getId()]->getFilesize();
                }
            }

            if ($line->getUser()->getId() === $guest_id) {
                if (!isset($summary['guests_ip'][$line->getIp()])) {
                    $summary['guests_ip'][$line->getIp()] = 0;
                }

                $summary['guests_ip'][$line->getIp()]++;
            }

            if (isset($username_of[$line->getUser()->getId()])) {
                $user_string = $username_of[$line->getUser()->getId()];
            } else {
                $user_string = $line->getUser()->getId();
            }

            $tags_string = '';
            if ($line->getTagIds()) {
                $tags_string = preg_replace_callback(
                    '/(\d+)/',
                    fn($m) => /** @phpstan-ignore-next-line */
isset($name_of_tag[$m[1]]) ? $name_of_tag[$m[1]]['url'] : $m[1],
                    str_replace(
                        ',',
                        ', ',
                        $line->getTagIds()
                    )
                );
            }

            $image_string = $this->getImageString($this->image_std_params, $line, $image_infos, $rules);

            $search_results[] = [
                'DATE' => $line->getDate(),
                'TIME' => $line->getTime(),
                'USER' => $user_string,
                'IP' => $line->getIp(),
                'IMAGE' => $image_string,
                'TYPE' => $line->getImageType(),
                'SECTION' => $line->getSection(),
                'CATEGORY' => $line->getAlbum() ? ($name_of_category[$line->getAlbum()->getId()] ?? 'deleted ' . $line->getAlbum()->getId())
                    : '',
                'TAGS' => $tags_string,
            ];
        }

        $summary['nb_guests'] = 0;
        if (count(array_keys($summary['guests_ip'])) > 0) {
            $summary['nb_guests'] = count(array_keys($summary['guests_ip']));

            // we delete the "guest" from the $username_of hash so that it is avoided in next steps
            unset($username_of[$guest_id]);
        }

        $summary['nb_members'] = count($username_of);
        $member_strings = [];
        foreach ($username_of as $user_id => $user_name) {
            $member_strings[] = $user_name;
        }

        $search_summary = [
            'NB_LINES' => $this->translator->trans('number_of_lines_filtered', ['count' => $nb_lines], 'admin'),
            'FILESIZE' => $summary['total_filesize'] != 0 ? ceil($summary['total_filesize'] / 1024) . ' MB' : '',
            'USERS' => $this->translator->trans('number_of_users', ['count' => $summary['nb_members'] + $summary['nb_guests']], 'admin'),
            'MEMBERS' => $this->translator->trans('number_of_members', ['count' => $summary['nb_members']], 'admin') . ': ' . implode(', ', $member_strings),
            'GUESTS' => $this->translator->trans('number_of_guests', ['count' => $summary['nb_guests']], 'admin'),
        ];

        return [
            'nb_lines' => $nb_lines,
            'search_results' => $search_results,
            'search_summary' => $search_summary
        ];
    }

    /** @phpstan-ignore-next-line */
    protected function getImageString(ImageStandardParams $image_std_params, History $line, array $image_infos = [], SearchRulesModel $rules = null): string
    {
        $image_string = '';

        if ($line->getImage() && $line->getAlbum()) {
            $picture_url = $this->generateUrl('picture', ['image_id' => $line->getImage()->getId(), 'type' => 'category', 'element_id' => $line->getAlbum()->getId()]);

            if (isset($image_infos[$line->getImage()->getId()])) {
                $thumbnail_display = $rules->getDisplayThumbnail();
            } else {
                $thumbnail_display = 'no_display_thumbnail';
            }

            $image_title = '(' . $line->getImage()->getId() . ')';

            if ($image_infos[$line->getImage()->getId()]->getName()) {
                $image_title .= ' ' . $image_infos[$line->getImage()->getId()]->getName();
            } else {
                $image_title .= ' unknown filename';
            }

            $image_string = '';
            $thumb_url = '';

            if ($thumbnail_display === 'display_thumbnail_classic' || $thumbnail_display === 'display_thumbnail_hoverbox') {
                $params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);
                $derivative = new DerivativeImage($image_infos[$line->getImage()->getId()], $params, $image_std_params);
                $thumb_url = $this->generateUrl(
                    'admin_media',
                    [
                        'path' => $image_infos[$line->getImage()->getId()]->getPathBasename(),
                        'derivative' => $derivative->getUrlType(),
                        'image_extension' => $image_infos[$line->getImage()->getId()]->getExtension()
                    ]
                );
            }

            switch ($thumbnail_display) {
                case 'no_display_thumbnail':
                    {
                        $image_string = '<a href="' . $picture_url . '">' . $image_title . '</a>';
                        break;
                    }
                case 'display_thumbnail_classic':
                    {
                        $image_string =
                            '<a class="thumbnail" href="' . $picture_url . '">'
                            . '<span><img src="' . $thumb_url
                            . '" alt="' . $image_title . '" title="' . $image_title . '">'
                            . '</span></a>';
                        break;
                    }
                case 'display_thumbnail_hoverbox':
                    {
                        $image_string =
                            '<a class="over" href="' . $picture_url . '">'
                            . '<span><img src="' . $thumb_url
                            . '" alt="' . $image_title . '" title="' . $image_title . '">'
                            . '</span>' . $image_title . '</a>';
                        break;
                    }
            }
        }

        return $image_string;
    }

    protected function dateFormat(string $locale, int $timestamp, string $format): string
    {
        $date_time = (new \DateTime())->setTimestamp($timestamp);
        $intl_date_formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, $format);

        return $intl_date_formatter->format($date_time);
    }

    protected function refreshSummary(HistoryRepository $historyRepository, HistorySummaryRepository $historySummaryRepository): void
    {
        $need_update = [];

        $max_id = 0;
        $is_first = true;
        $first_time_key = null;
        $row = [];

        foreach ($historyRepository->getDetailsFromNotSummarized() as $row) {
            $history = $row[0];
            $time_keys = [
                $history->getDate()->format('Y'),
                $history->getDate()->format('Y-m'),
                $history->getDate()->format('Y-m-d'),
                sprintf(
                    '%s-%02u',
                    $history->getDate()->format('Y-m-d'),
                    $history->getTime()->format('h')
                ),
            ];

            foreach ($time_keys as $time_key) {
                if (!isset($need_update[$time_key])) {
                    $need_update[$time_key] = 0;
                }
                $need_update[$time_key] += $row['nb_pages'];
            }

            if ($row['max_id'] > $max_id) {
                $max_id = $row['max_id'];
            }

            if ($is_first) {
                $is_first = false;
                $first_time_key = $time_keys[3];
            }
        }

        // Only the oldest time_key might be already summarized, so we have to
        // update the 4 corresponding lines instead of simply inserting them.
        //
        // For example, if the oldest unsummarized is 2005.08.25.21, the 4 lines
        // that can be updated are:
        //
        // +---------------+----------+
        // | id            | nb_pages |
        // +---------------+----------+
        // | 2005          |   241109 |
        // | 2005-08       |    20133 |
        // | 2005-08-25    |      620 |
        // | 2005-08-25-21 |      151 |
        // +---------------+----------+


        $updates = [];

        if (isset($first_time_key)) {
            foreach ($historySummaryRepository->getSummaryToUpdate(...array_map('intval', explode('-', $first_time_key))) as $historySummary) {
                $key = sprintf('%4u', $historySummary->getYear());
                if ($historySummary->getMonth()) {
                    $key .= sprintf('-%02u', $historySummary->getMonth());
                    if ($historySummary->getDay()) {
                        $key .= sprintf('-%02u', $historySummary->getDay());
                        if ($historySummary->getHour()) {
                            $key .= sprintf('-%02u', $historySummary->getHour());
                        }
                    }
                }

                if (isset($need_update[$key])) {
                    $row['nb_pages'] += $need_update[$key];
                    $updates[] = $row;
                    unset($need_update[$key]);
                }
            }
        }

        foreach ($need_update as $time_key => $nb_pages) {
            $time_tokens = array_map('intval', explode('-', $time_key));

            $historySummary = new HistorySummary();
            $historySummary->setYear($time_tokens[0]);
            if (!empty($time_tokens[1])) {
                $historySummary->setMonth($time_tokens[1]);
            }
            if (!empty($time_tokens[2])) {
                $historySummary->setDay($time_tokens[2]);
            }
            if (!empty($time_tokens[3])) {
                $historySummary->setHour($time_tokens[3]);
            }
            $historySummary->setNbPages($nb_pages);
            $historySummaryRepository->addOrUpdateHistorySummary($historySummary);
        }

        if ($max_id != 0) {
            $historyRepository->setSummarizedForUnsummarized($max_id);
        }
    }
}
