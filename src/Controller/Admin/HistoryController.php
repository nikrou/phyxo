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
use App\Entity\HistorySummary;
use App\Entity\Search;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\SearchRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class HistoryController extends AbstractController
{
    private $image_std_params, $types, $display_thumbnails, $translator;

    public function __construct(ImageStandardParams $image_std_params, TranslatorInterface $translator)
    {
        $this->image_std_params = $image_std_params;
        $this->translator = $translator;

        $this->types = [
            'none' => $this->translator->trans('none', [], 'admin'),
            'picture' => $this->translator->trans('picture', [], 'admin'),
            'high' => $this->translator->trans('high', [], 'admin'),
            'other' => $this->translator->trans('other', [], 'admin')
        ];
        $this->display_thumbnails = [
            'no_display_thumbnail' => $this->translator->trans('No display', [], 'admin'),
            'display_thumbnail_classic' => $this->translator->trans('Classic display', [], 'admin'),
            'display_thumbnail_hoverbox' => $this->translator->trans('Hoverbox display', [], 'admin')
        ];
    }

    protected function setTabsheet(string $section = 'stats'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('stats', $this->translator->trans('Statistics', [], 'admin'), $this->generateUrl('admin_history'), 'fa-signal');
        $tabsheet->add('search', $this->translator->trans('Search', [], 'admin'), $this->generateUrl('admin_history_search'), 'fa-search');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function stats(Request $request, int $year = null, int $month = null, int $day = null, Conf $conf, HistorySummaryRepository $historySummaryRepository,
                        HistoryRepository $historyRepository)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $this->refreshSummary($historyRepository, $historySummaryRepository);

        $summary_lines = [];
        foreach ($historySummaryRepository->getSummary($year, $month, $day) as $historySummary) {
            $summary_lines[] = $historySummary;
        }

        $title_parts = [];
        $title_parts[] = '<a href="' . $this->generateUrl('admin_history') . '">' . $this->translator->trans('Overall', [], 'admin') . '</a>';

        $period_label = $this->translator->trans('Year', [], 'admin');
        if (!is_null($year)) {
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year', ['year' => $year]) . '">' . $year . '</a>';
            $period_label = $this->translator->trans('Month', [], 'admin');
        }

        if (!is_null($month)) {
            $month_title = $this->dateFormat(mktime(12, null, null, $month, 1, $year), 'LLLL');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month', ['year' => $year, 'month' => sprintf('%02d', $month)]) . '">' . $month_title . '</a>';
            $period_label = $this->translator->trans('Day', [], 'admin');
        }

        if (!is_null($day)) {
            $day_title = $this->dateFormat(mktime(12, null, null, $month, $day, $year), 'd (cccc)');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month_day', ['year' => $year, 'month' => sprintf('%02d', $month), 'day' => sprintf('%02d', $day)]) . '">' . $day_title . '</a>';
            $period_label = $this->translator->trans('Hour', [], 'admin');
        }

        $tpl_params['L_STAT_TITLE'] = implode($conf['level_separator'], $title_parts);
        $tpl_params['PERIOD_LABEL'] = $period_label;

        $max_width = 400;
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
        foreach ($summary_lines as $line) {
            if ($line->getNbPages() > $max_pages) {
                $max_pages = $line->getNbPages();
            }

            $datas[$line->$method()] = $line->getNbPages();
        }

        if (!isset($min_x) && !isset($max_x) && count($datas) > 0) {
            $min_x = min(array_keys($datas));
            $max_x = max(array_keys($datas));
        } else {
            $min_x = 0;
            $max_x = 0;
        }

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
                    $value = $this->dateFormat(mktime(12, null, null, $month, $i, $year), 'd (cccc)');
                } elseif (!is_null($year)) {
                    $url = $this->generateUrl('admin_history_year_month', ['year' => $year, 'month' => sprintf('%02d', $i)]);
                    $value = $this->dateFormat(mktime(12, null, null, $i, 1, $year), 'LLLL');
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
                    'WIDTH' => ceil(($datas[$i] * $max_width) / $max_pages)
                ];
            }
        }

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_history');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_history');
        $tpl_params['PAGE_TITLE'] = $this->translator->trans('History', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('stats'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('history_stats.html.twig', $tpl_params);
    }

    public function search(Request $request, SearchRepository $searchRepository, int $start, int $search_id = null, AlbumMapper $albumMapper, Conf $conf,
                            UserRepository $userRepository, UserMapper $userMapper, ImageMapper $imageMapper,
                            TagRepository $tagRepository, HistoryRepository $historyRepository)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['type_option_values'] = $this->types;
        $tpl_params['display_thumbnails'] = $this->display_thumbnails;

        if (!is_null($search_id)) {
            $rules = [];
            $search = $searchRepository->findOneBy(['id' => $search_id]);
            if (!is_null($search) && !empty($search->getRules())) {
                $rules = unserialize(base64_decode($search->getRules()));
            }

            $tpl_params['search_results'] = $this->getElementFromSearchRules(
                $rules, $start, $conf, $historyRepository,
                $albumMapper, $userMapper, $imageMapper, $userRepository, $tagRepository
            );
            $tpl_params['search_summary'] = $tpl_params['search_results']['search_summary'];
            $nb_lines = $tpl_params['search_results']['nb_lines'];

            $tpl_params['navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'admin_history_search',
                ['search_id' => $search_id],
                $nb_lines,
                $start,
                $conf['nb_logs_page']
              );
        }

        $tpl_params['display_thumbnail_selected'] = $request->request->get('display_thumbnail') ?? '';
        $tpl_params['type_option_selected'] = $request->request->get('types') ?? '';

        $tpl_params['user_options'] = [];
        foreach ($userRepository->findBy([], ['username' => 'ASC']) as $user) {
            $tpl_params['user_options'][$user->getId()] = $user->getUsername();
        }
        $tpl_params['user_options_selected'] = $request->request->get('user') ?? -1;

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_history_search_save');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_history');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_history_search');
        $tpl_params['PAGE_TITLE'] = $this->translator->trans('History', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('search'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('history_search.html.twig', $tpl_params);
    }

    protected function getElementFromSearchRules(array $rules, int $start, Conf $conf, HistoryRepository $historyRepository, AlbumMapper $albumMapper, UserMapper $userMapper,
                                            ImageMapper $imageMapper, UserRepository $userRepository, TagRepository $tagRepository): array
    {
        $search_results = [];

        if (isset($rules['fields']['filename'])) {
            $rules['image_ids'] = [];
            foreach ($imageMapper->getRepository()->findBy(['file' => $rules['fields']['filename']]) as $image) {
                $rules['image_ids'][] = $image->getId();
            }
        }

        $nb_lines = $historyRepository->getHistory($rules, $this->types, 0, 0, $count_only = true);

        $data = [];
        foreach ($historyRepository->getHistory($rules, $this->types, $conf['nb_logs_page'], $start * $conf['nb_logs_page']) as $history) {
            $data[] = $history;
        }
        usort($data, function ($a, $b) {
            return strcmp($a->getDate() . $a->getTime(), $b->getDate() . $b->getTime());
        });

        $history_lines = [];
        $user_ids = [];
        $username_of = [];
        $category_ids = [];
        $image_ids = [];
        $has_tags = false;

        foreach ($data as $row) {
            $user_ids[] = $row['user_id'];

            if (isset($row['category_id'])) {
                $category_ids[] = $row['category_id'];
            }

            if (isset($row['image_id'])) {
                $image_ids[] = $row['image_id'];
            }

            if (isset($row['tag_ids'])) {
                $has_tags = true;
            }

            $history_lines[] = $row;
        }

        if (count($user_ids) > 0) {
            $username_of = [];
            foreach ($userRepository->findBy(['id' => $user_ids]) as $user) {
                $username_of[$user->getId()] = $user->getUsername();
            }
        }

        if (count($category_ids) > 0) {
            $uppercats_of = [];
            foreach ($albumMapper->getRepository()->findById($category_ids) as $album) {
                $uppercats_of[$album->getId()] = $album->getUppercats();
            }

            $name_of_category = [];
            foreach ($uppercats_of as $category_id => $uppercats) {
                $name_of_category[$category_id] = $albumMapper->getAlbumsDisplayNameCache($uppercats);
            }
        }

        $image_infos = [];
        if (count($image_ids) > 0) {
            foreach ($imageMapper->getRepository()->findBy(['id' => array_keys($image_ids)]) as $image) {
                $image_infos[$image->getId()] = $image;
            }
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

        $i = 0;
        $first_line = $start + 1;
        $last_line = $start + $conf['nb_logs_page'];

        $summary = [];
        $summary['total_filesize'] = 0;
        $summary['guests_ip'] = [];

        $guest_id = $userMapper->getDefaultUser()->getId();

        foreach ($history_lines as $line) {
            if (isset($line['image_type']) && $line['image_type'] === 'high') {
                if (isset($image_infos[$line['image_id']]['filesize'])) {
                    $summary['total_filesize'] += $image_infos[$line['image_id']]->getFilesize();
                }
            }

            if ($line['user_id'] === $guest_id) {
                if (!isset($summary['guests_ip'][$line['ip']])) {
                    $summary['guests_ip'][$line['ip']] = 0;
                }

                $summary['guests_ip'][$line['ip']]++;
            }

            $i++;

            if ($i < $first_line or $i > $last_line) {
                continue;
            }

            if (isset($username_of[$line['user_id']])) {
                $user_string = $username_of[$line['user_id']];
            } else {
                $user_string = $line['user_id'];
            }

            $tags_string = '';
            if (isset($line['tag_ids'])) {
                $tags_string = preg_replace_callback(
                    '/(\d+)/',
                    function ($m) use ($name_of_tag) {
                        return isset($name_of_tag[$m[1]]) ? $name_of_tag[$m[1]]['url'] : $m[1];
                    },
                    str_replace(
                        ',',
                        ', ',
                        $line['tag_ids']
                    )
                );
            }

            $image_string = $this->getImageString($line, $image_infos, $rules, $conf, $this->image_std_params);

            $search_results[] = [
                'DATE' => $line['date'],
                'TIME' => (($pos = strrpos($line['time'], '.')) !== false) ? substr($line['time'], 0, $pos) : $line['time'],
                'USER' => $user_string,
                'IP' => $line['ip'],
                'IMAGE' => $image_string,
                'TYPE' => $line['image_type'],
                'SECTION' => $line['section'],
                'CATEGORY' => isset($line['category_id'])
                    ? (isset($name_of_category[$line['category_id']])
                    ? $name_of_category[$line['category_id']]
                    : 'deleted ' . $line['category_id'])
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

    protected function getImageString(array $line = [], array $image_infos = [], array $search = [], Conf $conf, ImageStandardParams $image_std_params): string
    {
        $image_string = '';
        $element = [];

        if (isset($line['image_id'])) {
            $picture_url = $this->generateUrl('picture', ['image_id' => $line['image_id']]); // @FIX: missing other param

            if (isset($image_infos[$line['image_id']])) {
                $element = [
                    'id' => $line['image_id'],
                    'file' => $image_infos[$line['image_id']]->getFile(),
                    'path' => $image_infos[$line['image_id']]->getPath(),
                    'representative_ext' => $image_infos[$line['image_id']]->getRepresentativeExt(),
                ];
                $thumbnail_display = $search['fields']['display_thumbnail'];
            } else {
                $thumbnail_display = 'no_display_thumbnail';
            }

            $image_title = '(' . $line['image_id'] . ')';

            if (isset($image_infos[$line['image_id']]['label'])) {
                $image_title .= ' ' . $image_infos[$line['image_id']]['label'];
            } else {
                $image_title .= ' unknown filename';
            }

            $image_string = '';
            $thumb_url = '';

            if ($thumbnail_display === 'display_thumbnail_classic' || $thumbnail_display === 'display_thumbnail_hoverbox') {
                $src_image = new SrcImage($element, $conf['picture_ext']);
                $params = $image_std_params->getByType(ImageStandardParams::IMG_THUMB);
                $thumb_url = (new DerivativeImage($src_image, $params, $image_std_params))->getUrl();
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

    public function saveSearch(Request $request, SearchRepository $searchRepository)
    {
        if ($request->isMethod('POST')) {
            $rules = [];
            if ($date_after = $request->request->get('start')) {
                $rules['fields']['date-after'] = $date_after;
            }

            if ($date_end = $request->request->get('end')) {
                $rules['fields']['date-before'] = $date_end;
            }

            if ($types = $request->request->get('types')) {
                $rules['fields']['types'] = $types;
            } else {
                $rules['fields']['types'] = $this->types;
            }

            $rules['fields']['user'] = $request->request->get('user');

            if ($image_id = $request->request->get('image_id')) {
                $rules['fields']['image_id'] = intval($image_id);
            }

            if ($filename = $request->request->get('filename')) {
                $rules['fields']['filename'] = str_replace('*', '%', $filename);
            }

            if ($ip = $request->request->get('ip')) {
                $rules['fields']['ip'] = str_replace('*', '%', $ip);
            }

            $rules['fields']['display_thumbnail'] = $request->request->get('display_thumbnail');
            // Display choice are also save to one cookie
            $cookie_val = null;
            if ($display_thumbnail = $request->request->get('display_thumbnail')) {
                if ($this->display_thumbnails[$display_thumbnail]) {
                    $cookie_val = $display_thumbnail;
                }
            }
            setcookie('display_thumbnail', $cookie_val, strtotime('+1 month'), $request->getBasePath());

            // TODO manage inconsistency of having $_POST['image_id'] and $_POST['filename'] simultaneously
            if (!empty($rules)) {
                $search = new Search();
                $search->setRules(base64_encode(serialize($rules)));
                $searchRepository->addSearch($search);

                return $this->redirectToRoute('admin_history_search', ['search_id' => $search->getId()]);
            } else {
                $this->addFlash('error', $this->translator->trans('Empty query. No criteria has been entered.', [], 'admin'));
                return $this->redirectToRoute('admin_history_search');
            }
        }
        return $this->redirectToRoute('admin_history_search');
    }

    protected function dateFormat(int $timestamp, string $format): string
    {
        $date_time = (new \DateTime())->setTimestamp($timestamp);
        $intl_date_formatter = new \IntlDateFormatter($this->getUser()->getLanguage(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, $format);

        return $intl_date_formatter->format($date_time);
    }

    protected function refreshSummary(HistoryRepository $historyRepository, HistorySummaryRepository $historySummaryRepository)
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
            foreach ($historySummaryRepository->getSummaryToUpdate(...explode('-', $first_time_key)) as $historySummary) {
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
            $time_tokens = explode('-', $time_key);

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
