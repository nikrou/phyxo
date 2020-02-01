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

namespace App\Repository;

class CalendarRepository extends BaseRepository
{
    public function findImagesInPeriodsByIds(string $level, array $ids = [], string $date_where = '')
    {
        return (new ImageRepository($this->conn))->findImagesInPeriodsByIds($level, $ids, $date_where);
    }

    public function getSubcatIds(array $ids) : array
    {
        return (new CategoryRepository($this->conn))->getSubcatIds([$ids]);
    }

    public function findImagesInPeriods(string $level, string $date_where = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findImagesInPeriods($level, $date_where, $condition, $category_ids);
    }

    public function findNextPrevPeriodByIds(array $ids = [], array $date_elements, array $calendar_levels, string $date_field = '')
    {
        return (new ImageRepository($this->conn))->findNextPrevPeriodByIds($ids, $date_elements, $calendar_levels, $date_field);
    }

    public function findNextPrevPeriod(array $date_elements, array $calendar_levels, string $date_field = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findNextPrevPeriod($date_elements, $calendar_levels, $date_field, $condition, $category_ids);
    }

    public function findDistincIds(string $condition, array $category_ids = [], string $order_by)
    {
        return (new ImageRepository($this->conn))->findDistincIds($condition, $category_ids, $order_by);
    }

    public function findYYYYMMPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        return (new ImageRepository($this->conn))->findYYYYMMPeriodAndImagesCountByIds($date_field, $date_where, $ids);
    }

    public function findYYYYMMPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findYYYYMMPeriodAndImagesCount($date_field, $date_where, $condition, $category_ids);
    }

    public function findMMDDPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        return (new ImageRepository($this->conn))->findMMDDPeriodAndImagesCountByIds($date_field, $date_where, $ids);
    }

    public function findMMDDPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findMMDDPeriodAndImagesCount($date_field, $date_where, $condition, $category_ids);
    }

    public function findOneRandomInWeekByIds(string $date_field, string $date_where = '', array $ids)
    {
        return (new ImageRepository($this->conn))->findOneRandomInWeekByIds($date_field, $date_where, $ids);
    }

    public function findOneRandomInWeek(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findOneRandomInWeek($date_field, $date_where, $condition, $category_ids);
    }

    public function findDayOfMonthPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        return (new ImageRepository($this->conn))->findDayOfMonthPeriodAndImagesCountByIds($date_field, $date_where, $ids);
    }

    public function findDayOfMonthPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        return (new ImageRepository($this->conn))->findDayOfMonthPeriodAndImagesCount($date_field, $date_where, $condition, $category_ids);
    }
}
