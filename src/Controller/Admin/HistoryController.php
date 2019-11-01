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

use App\DataMapper\CategoryMapper;
use App\Repository\CategoryRepository;
use App\Repository\HistoryRepository;
use App\Repository\HistorySummaryRepository;
use App\Repository\ImageRepository;
use App\Repository\SearchRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\Functions\Plugin;
use Phyxo\Functions\URL;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class HistoryController extends AdminCommonController
{
    private $image_std_params, $types, $display_thumbnails;

    public function __construct(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;

        $this->types = ['none', 'picture', 'high', 'other'];
        $this->display_thumbnails = [
            'no_display_thumbnail' => Language::l10n('No display'),
            'display_thumbnail_classic' => Language::l10n('Classic display'),
            'display_thumbnail_hoverbox' => Language::l10n('Hoverbox display')
        ];
    }

    protected function setTabsheet(string $section = 'stats'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('stats', Language::l10n('Statistics'), $this->generateUrl('admin_history'), 'fa-signal');
        $tabsheet->add('search', Language::l10n('Search'), $this->generateUrl('admin_history_search'), 'fa-search');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function stats(Request $request, int $year = null, int $month = null, int $day = null, Template $template, Conf $conf, EntityManager $em, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $this->refreshSummary($em);

        $result = $em->getRepository(HistorySummaryRepository::class)->getSummary($year, $month, $day);
        $summary_lines = $em->getConnection()->result2array($result);

        $title_parts = [];
        $title_parts[] = '<a href="' . $this->generateUrl('admin_history') . '">' . Language::l10n('Overall') . '</a>';

        $period_label = Language::l10n('Year');
        if (!is_null($year)) {
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year', ['year' => $year]) . '">' . $year . '</a>';
            $period_label = Language::l10n('Month');
        }

        if (!is_null($month)) {
            $month_title = $this->dateFormat(mktime(12, null, null, $month, 1, $year), 'LLLL');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month', ['year' => $year, 'month' => sprintf('%02d', $month)]) . '">' . $month_title . '</a>';
            $period_label = Language::l10n('Day');
        }

        if (!is_null($day)) {
            $day_title = $this->dateFormat(mktime(12, null, null, $month, $day, $year), 'd (cccc)');
            $title_parts[] = '<a href="' . $this->generateUrl('admin_history_year_month_day', ['year' => $year, 'month' => sprintf('%02d', $month), 'day' => sprintf('%02d', $day)]) . '">' . $day_title . '</a>';
            $period_label = Language::l10n('Hour');
        }

        $tpl_params['L_STAT_TITLE'] = implode($conf['level_separator'], $title_parts);
        $tpl_params['PERIOD_LABEL'] = $period_label;

        $max_width = 400;
        $datas = [];

        if (!is_null($day)) {
            $key = 'hour';
            $min_x = 0;
            $max_x = 23;
        } elseif (!is_null($month)) {
            $key = 'day';
            $min_x = 1;
            $max_x = date('t', mktime(12, 0, 0, $month, 1, $year));
        } elseif (!is_null($year)) {
            $key = 'month';
            $min_x = 1;
            $max_x = 12;
        } else {
            $key = 'year';
        }

        $max_pages = 1;
        foreach ($summary_lines as $line) {
            if ($line['nb_pages'] > $max_pages) {
                $max_pages = $line['nb_pages'];
            }

            $datas[$line[$key]] = $line['nb_pages'];
        }

        if (!isset($min_x) and !isset($max_x) and count($datas) > 0) {
            $min_x = min(array_keys($datas));
            $max_x = max(array_keys($datas));
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
        $tpl_params['PAGE_TITLE'] = Language::l10n('History');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('stats'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('history_stats.tpl', $tpl_params);
    }

    public function search(Request $request, int $start, int $search_id = null, CategoryMapper $categoryMapper, Template $template, Conf $conf, EntityManager $em, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['type_option_values'] = $this->types;
        $tpl_params['display_thumbnails'] = $this->display_thumbnails;

        if (!is_null($search_id)) {
            $searchRules = $this->getSearchRules($search_id, $start, $conf, $em, $categoryMapper);

            $nb_lines = $searchRules['nb_lines'];
            $tpl_params['search_results'] = $searchRules['search_results'];
            $tpl_params['search_summary'] = $searchRules['search_summary'];
            unset($searchRules);

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
        $tpl_params['type_option_selected'] = $request->request->get('types') ?? [];

        $result = $em->getRepository(UserRepository::class)->findAll('ORDER BY username ASC');
        $tpl_params['user_options'] = $em->getConnection()->result2array($result, 'id', 'username');
        $tpl_params['user_options_selected'] = $request->request->get('user') ?? -1;


        $tpl_params['F_ACTION'] = $this->generateUrl('admin_history_search_save');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_history');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_history_search');
        $tpl_params['PAGE_TITLE'] = Language::l10n('History');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('search'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('history_search.tpl', $tpl_params);
    }

    protected function getSearchRules(int $search_id, int $start, Conf $conf, EntityManager $em, CategoryMapper $categoryMapper): array
    {
        $search_results = [];

        $result = $em->getRepository(SearchRepository::class)->findById($search_id);
        list($serialized_rules) = $em->getConnection()->db_fetch_row($result);

        $search = unserialize(base64_decode($serialized_rules));

        if (isset($search['fields']['filename'])) {
            $result = $em->getRepository(ImageRepository::class)->findByFields('file', $search['fields']['filename']);
            $search['image_ids'] = $em->getConnection()->result2array($result, null, 'id');

            // merge avec page['search'] ????
        }

        $nb_lines = $em->getRepository(HistoryRepository::class)->getHistory($search, $this->types, 0, 0, $count_only = true);

        $result = $em->getRepository(HistoryRepository::class)->getHistory($search, $this->types, $conf['nb_logs_page'], $start * $conf['nb_logs_page']);
        $data = $em->getConnection()->result2array($result);
        usort($data, function ($a, $b) {
            return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
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
            $result = $em->getRepository(UserRepository::class)->findByIds($user_ids);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $username_of[$row['id']] = stripslashes($row['username']);
            }
        }

        if (count($category_ids) > 0) {
            $result = $em->getRepository(CategoryRepository::class)->findByIds($category_ids);
            $uppercats_of = $em->getConnection()->result2array($result, 'id', 'uppercats');

            $name_of_category = [];

            foreach ($uppercats_of as $category_id => $uppercats) {
                $name_of_category[$category_id] = $categoryMapper->getCatDisplayNameCache($uppercats);
            }
        }

        if (count($image_ids) > 0) {
            $result = $em->getRepository(ImageRepository::class)->findByIds(array_keys($image_ids));
            $image_infos = $em->getConnection()->result2array($result, 'id');
        }

        if ($has_tags > 0) {
            $name_of_tag = [];
            $result = $em->getRepository(TagRepository::class)->findAll();
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $name_of_tag[$row['id']] = [
                    'name' => Plugin::trigger_change("render_tag_name", $row['name'], $row),
                    'url' => '<a href="' . $this->generateUrl('images_by_tags', ['tag_ids' => URL::tagToUrl($row)]) . '">' . $row['name'] . '</a>',
                ];
            }
        }

        $i = 0;
        $first_line = $start + 1;
        $last_line = $start + $conf['nb_logs_page'];

        $summary = [];
        $summary['total_filesize'] = 0;
        $summary['guests_ip'] = [];

        foreach ($history_lines as $line) {
            if (isset($line['image_type']) && $line['image_type'] === 'high') {
                if (isset($image_infos[$line['image_id']]['filesize'])) {
                    $summary['total_filesize'] += intval($image_infos[$line['image_id']]['filesize']);
                }
            }

            if ($line['user_id'] === $conf['guest_id']) {
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

            $image_string = $this->getImageString($line, $image_infos, $search, $conf, $this->image_std_params);

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
            unset($username_of[$conf['guest_id']]);
        }

        $summary['nb_members'] = count($username_of);
        $member_strings = [];
        foreach ($username_of as $user_id => $user_name) {
            $member_strings[] = $user_name;
        }

        $search_summary = [
            'NB_LINES' => Language::l10n_dec(
                '%d line filtered',
                '%d lines filtered',
                $nb_lines
            ),
            'FILESIZE' => $summary['total_filesize'] != 0 ? ceil($summary['total_filesize'] / 1024) . ' MB' : '',
            'USERS' => \Phyxo\Functions\Language::l10n_dec(
                '%d user',
                '%d users',
                $summary['nb_members'] + $summary['nb_guests']
            ),
            'MEMBERS' => sprintf(
                Language::l10n_dec('%d member', '%d members', $summary['nb_members']) . ': %s',
                implode(', ', $member_strings)
            ),
            'GUESTS' => Language::l10n_dec(
                '%d guest',
                '%d guests',
                $summary['nb_guests']
            ),
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

        if (isset($line['image_id'])) {
            $picture_url = $this->generateUrl('picture', ['image_id' => $line['image_id']]); // @FIX: missing other param

            if (isset($image_infos[$line['image_id']])) {
                $element = [
                    'id' => $line['image_id'],
                    'file' => $image_infos[$line['image_id']]['file'],
                    'path' => $image_infos[$line['image_id']]['path'],
                    'representative_ext' => $image_infos[$line['image_id']]['representative_ext'],
                ];
                $thumbnail_display = $search['fields']['display_thumbnail'];
            } else {
                $thumbnail_display = 'no_display_thumbnail';
            }

            $image_title = '(' . $line['image_id'] . ')';

            if (isset($image_infos[$line['image_id']]['label'])) {
                $image_title .= ' ' . Plugin::trigger_change('render_element_description', $image_infos[$line['image_id']]['label']);
            } else {
                $image_title .= ' unknown filename';
            }

            $image_string = '';

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

    public function saveSearch(Request $request, EntityManager $em)
    {
        if ($request->isMethod('POST')) {
            $search = [];
            if ($date_after = $request->request->get('start')) {
                $search['fields']['date-after'] = $date_after;
            }

            if ($date_end = $request->request->get('end')) {
                $search['fields']['date-before'] = $date_end;
            }

            if ($types = $request->request->get('types')) {
                $search['fields']['types'] = $types;
            } else {
                $search['fields']['types'] = $this->types;
            }

            $search['fields']['user'] = $request->request->get('user');

            if ($image_id = $request->request->get('image_id')) {
                $search['fields']['image_id'] = intval($image_id);
            }

            if ($filename = $request->request->get('filename')) {
                $search['fields']['filename'] = str_replace('*', '%', $em->getConnection()->db_real_escape_string($filename));
            }

            if ($ip = $request->request->get('ip')) {
                $search['fields']['ip'] = str_replace('*', '%', $em->getConnection()->db_real_escape_string($ip));
            }

            $search['fields']['display_thumbnail'] = $request->request->get('display_thumbnail');
            // Display choice are also save to one cookie
            $cookie_val = null;
            if ($display_thumbnail = $request->request->get('display_thumbnail')) {
                if ($this->display_thumbnails[$display_thumbnail]) {
                    $cookie_val = $display_thumbnail;
                }
            }
            setcookie('display_thumbnail', $cookie_val, strtotime('+1 month'), $request->getBasePath());

            // TODO manage inconsistency of having $_POST['image_id'] and $_POST['filename'] simultaneously
            if (!empty($search)) {
                // register search rules in database, then they will be available on thumbnails page and picture page.
                $search_id = $em->getRepository(SearchRepository::class)->addSearch(base64_encode(serialize($search)));

                return $this->redirectToRoute('admin_history_search', ['search_id' => $search_id]);
            } else {
                $this->addFlash('error', Language::l10n('Empty query. No criteria has been entered.'));
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

    protected function refreshSummary(EntityManager $em)
    {
        $result = $em->getRepository(HistoryRepository::class)->getDetailsFromNotSummarized();
        $need_update = [];

        $max_id = 0;
        $is_first = true;
        $first_time_key = null;

        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $time_keys = [
                substr($row['date'], 0, 4), //yyyy
                substr($row['date'], 0, 7), //yyyy-mm
                substr($row['date'], 0, 10), //yyyy-mm-dd
                sprintf(
                    '%s-%02u',
                    $row['date'],
                    $row['hour']
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
        $inserts = [];

        if (isset($first_time_key)) {
            $result = $em->getRepository(HistorySummaryRepository::class)->getSummaryToUpdate(...explode('-', $first_time_key));
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $key = sprintf('%4u', $row['year']);
                if (isset($row['month'])) {
                    $key .= sprintf('-%02u', $row['month']);
                    if (isset($row['day'])) {
                        $key .= sprintf('-%02u', $row['day']);
                        if (isset($row['hour'])) {
                            $key .= sprintf('-%02u', $row['hour']);
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

            $inserts[] = [
                'year' => $time_tokens[0],
                'month' => $time_tokens[1] ?? null,
                'day' => $time_tokens[2] ?? null,
                'hour' => $time_tokens[3] ?? null,
                'nb_pages' => $nb_pages,
            ];
        }

        if (count($updates) > 0) {
            $em->getRepository(HistorySummaryRepository::class)->massUpdates(
              [
                  'primary' => ['year', 'month', 'day', 'hour'],
                  'update' => ['nb_pages'],
              ],
              $updates
            );
        }

        if (count($inserts) > 0) {
            $em->getRepository(HistorySummaryRepository::class)->massInserts(array_keys($inserts[0]), $inserts);
        }

        if ($max_id != 0) {
            $em->getRepository(HistoryRepository::class)->setSummarizedForUnsummarized($max_id);
        }
    }
}
