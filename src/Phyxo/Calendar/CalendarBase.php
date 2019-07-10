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

namespace Phyxo\Calendar;

use App\Repository\ImageRepository;
use App\Repository\CategoryRepository;
use Phyxo\DBLayer\iDBLayer;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Template\Template;
use Phyxo\Conf;
use Symfony\Component\Routing\RouterInterface;
use Phyxo\Functions\Language;

/**
 * Base class for monthly and weekly calendar styles
 */
abstract class CalendarBase
{
    const CAL_VIEW_LIST = 'list';
    const CAL_VIEW_CALENDAR = 'calendar';

    /** db column on which this calendar works */
    public $date_field;
    protected $find_by_items = false;
    protected $condition = '';
    protected $category_id = null;
    protected $forbidden_categories = [];
    protected $items = [];

    protected $conn;
    protected $parts;
    protected $image_std_params;
    protected $lang = [];
    protected $conf, $template, $date_type, $view_type, $chronology_date = [], $router;

    public function __construct(iDBLayer $conn, string $date_type = 'posted')
    {
        $this->conn = $conn;

        $this->date_type = $date_type;
        if ($date_type === 'posted') {
            $this->date_field = 'date_available';
        } else {
            $this->date_field = 'date_creation';
        }
    }

    public function setConf(Conf $conf)
    {
        $this->conf = $conf;
    }

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function setTemplate(Template $template)
    {
        $this->template = $template;
    }

    public function setViewType(string $view_type = self::CAL_VIEW_LIST)
    {
        $this->view_type = $view_type;
    }

    public function setChronologyDate(array $chronology_date = [])
    {
        $this->chronology_date = $chronology_date;
    }

    public function setImageStandardParams(ImageStandardParams $image_std_params)
    {
        $this->image_std_params = $image_std_params;
    }

    public function setLang(array $lang)
    {
        $this->lang = $lang;
    }

    public function findByConditionAndCategory(string $condition, int $category_id = null, array $forbidden_categories = [])
    {
        $this->find_by_items = false;
        $this->condition = $condition;
        $this->category_id = $category_id;
        $this->forbidden_categories = $forbidden_categories;
    }

    public function findByCondition(string $condition)
    {
        $this->find_by_items = false;
        $this->condition = $condition;
    }

    public function findByItems(array $items)
    {
        $this->find_by_items = true;
        $this->items = $items;
    }

    public function areThumbnailsIncluded(): bool
    {
        return ($this->view_type === self::CAL_VIEW_CALENDAR);
    }

    public abstract function getCalendarLevels(): array;

    protected abstract function urlFromDateComponents($item, array $date_components = []): string;

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    public abstract function generateCategoryContent(): bool;

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     */
    public abstract function getDateWhere(int $max_levels = 3): string;

    public function getBreadcrumb(string $route, array $params): array
    {
        $elements = [];

        $elements[] = [
            'label' => $this->date_type === 'posted' ? Language::l10n('Post date') : Language::l10n('Creation date'),
            'url' => $this->router->generate($route, $params)
        ];

        for ($i = 0; $i < count($this->chronology_date); $i++) {
            $element = [];
            $element['label'] = $this->getDateComponentLabel($i, $this->chronology_date[$i]);
            if (isset($this->chronology_date[$i + 1])) { // parts property defined in child class
                $route .= '_' . $this->parts[$i];
                $params[$this->parts[$i]] = $this->chronology_date[$i];
                $element['url'] = $this->router->generate($route, $params);
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * Returns the calendar title (with HTML).
     */
    public function getDisplayName(): string
    {
        $res = '';

        for ($i = 0; $i < count($this->chronology_date); $i++) {
            $res .= $this->conf['level_separator'];
            if (isset($this->chronology_date[$i + 1])) {
                $chronology_date = array_slice($this->chronology_date, 0, $i + 1);
                $url = \Phyxo\Functions\URL::duplicate_index_url(
                    ['chronology_date' => $chronology_date],
                    ['start']
                );
                $res .= '<a href="' . $url . '">' . $this->getDateComponentLabel($i, $this->chronology_date[$i]) . '</a>';
            } else {
                $res .= '<span class="calInHere">' . $this->getDateComponentLabel($i, $this->chronology_date[$i]) . '</span>';
            }
        }

        return $res;
    }

    /**
     * Returns a display name for a date component optionally using labels.
     */
    protected function getDateComponentLabel($level, $date_component): string
    {
        $label = $date_component;
        if (isset($this->getCalendarLevels()[$level]['labels'][$date_component])) {
            $label = $this->getCalendarLevels()[$level]['labels'][$date_component];
        } elseif ('any' === $date_component) {
            $label = Language::l10n('All');
        }

        return $label;
    }

    /**
     * Gets a nice display name for a date to be shown in previous/next links
     *
     * @param string $date
     */
    protected function getDateNiceName($date): string
    {
        $date_components = explode('-', $date);
        $res = '';
        for ($i = count($date_components) - 1; $i >= 0; $i--) {
            if ('any' !== $date_components[$i]) {
                $label = $this->getDateComponentLabel($i, $date_components[$i]);
                if ($res != '') {
                    $res .= ' ';
                }
                $res .= $label;
            }
        }

        return $res;
    }

    /**
     * Creates a calendar navigation bar.
     *
     * @param array $date_components
     * @param array $items - hash of items to put in the bar (e.g. 2005,2006)
     * @param bool $show_any - adds any link to the end of the bar
     * @param bool $show_empty - shows all labels even those without items
     * @param array $labels - optional labels for items (e.g. Jan,Feb,...)
     */
    protected function getNavigationBarFromItems(array $date_components, array $items, bool $show_any, bool $show_empty = false, array $labels = []): array
    {
        $nav_bar_datas = [];

        if ($this->conf['calendar_show_empty'] && $show_empty && !empty($labels)) {
            foreach ($labels as $item => $label) {
                if (!isset($items[$item])) {
                    $items[$item] = -1;
                }
            }
        }
        ksort($items);

        foreach ($items as $item => $nb_images) {
            $label = $item;
            if (isset($labels[$item])) {
                $label = $labels[$item];
            }
            if ($nb_images == -1) {
                $tmp_datas = [
                    'LABEL' => $label
                ];
            } else {
                $url = $this->urlFromDateComponents($item, $date_components);
                $tmp_datas = [
                    'LABEL' => $label,
                    'URL' => $url
                ];
            }
            if ($nb_images > 0) {
                $tmp_datas['NB_IMAGES'] = $nb_images;
            }
            $nav_bar_datas[] = $tmp_datas;
        }

        if ($this->conf['calendar_show_any'] && $show_any && count($items) > 1 && count($date_components) < count($this->getCalendarLevels()) - 1) {
            $url = \Phyxo\Functions\URL::duplicate_index_url(
                ['chronology_date' => array_merge($date_components, ['any'])],
                ['start']
            );
            $nav_bar_datas[] = [
                'LABEL' => Language::l10n('All'),
                'URL' => $url
            ];
        }

        return $nav_bar_datas;
    }

    /**
     * Creates a calendar navigation bar for a given level.
     *
     * @param int $level - 0-year, 1-month/week, 2-day
     */
    protected function buildNavigationBar(int $level, $labels = [])
    {
        if ($this->find_by_items) {
            $result = (new ImageRepository($this->conn))->findImagesInPeriodsByIds($this->getCalendarLevels()[$level]['sql'], $this->items, $this->getDateWhere($level));
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $result = (new ImageRepository($this->conn))->findImagesInPeriods($this->getCalendarLevels()[$level]['sql'], $this->getDateWhere($level), $this->condition, $sub_ids);
        }

        $level_items = $this->conn->result2array($result, 'period', 'nb_images');

        if (count($level_items) == 1 && count($this->chronology_date) < count($this->getCalendarLevels()) - 1) {
            if (!isset($this->chronology_date[$level])) {
                list($key) = array_keys($level_items);
                $this->chronology_date[$level] = (int)$key;

                if ($level < count($this->chronology_date) && $level != count($this->getCalendarLevels()) - 1) {
                    return;
                }
            }
        }

        $dates = $this->chronology_date;
        while ($level < count($dates)) {
            array_pop($dates);
        }

        $nav_bar = $this->getNavigationBarFromItems(
            $dates,
            $level_items,
            true,
            true,
            $labels
        );

        $this->template->append('chronology_navigation_bars', ['items' => $nav_bar]);
    }

    /**
     * Assigns the next/previous link to the template with regards to
     * the currently choosen date.
     */
    protected function buildNextPrev()
    {
        $prev = $next = null;
        if (empty($this->chronology_date)) {
            return;
        }

        if ($this->find_by_items) {
            $result = (new ImageRepository($this->conn))->findNextPrevPeriodByIds($this->items, $this->chronology_date, $this->getCalendarLevels(), $this->date_field);
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }
            $result = (new ImageRepository($this->conn))->findNextPrevPeriod($this->chronology_date, $this->getCalendarLevels(), $this->date_field, $this->condition, $sub_ids);
        }

        $upper_items = $this->conn->result2array($result, null, 'period');
        $current = implode('-', $this->chronology_date);

        usort($upper_items, 'version_compare');
        $upper_items_rank = array_flip($upper_items);
        if (!isset($upper_items_rank[$current])) {
            $upper_items[] = $current;// just in case (external link)
            usort($upper_items, 'version_compare');
            $upper_items_rank = array_flip($upper_items);
        }
        $current_rank = $upper_items_rank[$current];

        $tpl_var = [];

        if ($current_rank > 0) { // has previous
            $prev = $upper_items[$current_rank - 1];
            $chronology_date = explode('-', $prev);
            $tpl_var['previous'] = [
                'LABEL' => $this->getDateNiceName($prev),
                'URL' => $this->getNextPrevUrl($chronology_date)
            ];
        }

        if ($current_rank < count($upper_items) - 1) { // has next
            $next = $upper_items[$current_rank + 1];
            $chronology_date = explode('-', $next);

            $tpl_var['next'] = [
                'LABEL' => $this->getDateNiceName($next),
                'URL' => $this->getNextPrevUrl($chronology_date)
            ];
        }

        if (!empty($tpl_var)) {
            $existing = $this->template->getVariable('chronology_navigation_bars');
            if (!($existing instanceof \Smarty_Undefined_Variable)) {
                $existing->value[sizeof($existing->value) - 1] = array_merge($existing->value[sizeof($existing->value) - 1], $tpl_var);
            } else {
                $this->template->append('chronology_navigation_bars', $tpl_var);
            }
        }
    }

    public function getItems(string $order_by)
    {
        if ($this->find_by_items) {
            return $this->items;
        } else {
            if (!is_null($this->category_id) && !empty($this->forbidden_categories)) {
                $sub_ids = array_diff(
                    (new CategoryRepository($this->conn))->getSubcatIds([$this->category_id]),
                    $this->forbidden_categories
                );
            } else {
                $sub_ids = [];
            }

            $result = (new ImageRepository($this->conn))->findDistincIds($this->condition, $sub_ids, $order_by);

            return $this->conn->result2array($result, null, 'id');
        }
    }
}
