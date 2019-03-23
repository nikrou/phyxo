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

namespace App\Entity;

class UserInfos implements \ArrayAccess
{
    private $infos = [];

    public function __construct(array $infos = [])
    {
        $this->infos = $infos;
    }

    public function asArray()
    {
        return $this->infos;
    }

    public function offsetExists($offset)
    {
        return isset($this->infos[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->infos[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->infos[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->infos[$offset]);
    }

    public function getTheme()
    {
        return $this->infos['theme'] ?? null;
    }

    public function getNbImagePage()
    {
        return $this->infos['nb_image_page'] ?? null;
    }

    public function getNbAvailableTags()
    {
        return $this->infos['nb_available_tags'] ?? null;
    }

    public function setNbAvailableTags(int $number_of_tags)
    {
        $this->infos['nb_available_tags'] = $number_of_tags;
    }

    public function setNbAvailableComments(int $number_of_comments)
    {
        $this->infos['nb_available_comments'] = $number_of_comments;
    }

    public function getNbTotalImages()
    {
        return $this->infos['nb_total_images'] ?? null;
    }

    public function getRecentPeriod()
    {
        return $this->infos['recent_period'] ?? null;
    }

    public function getShowNbHits()
    {
        return $this->infos['show_nb_hits'] ?? null;
    }

    public function getShowNbComments()
    {
        return $this->infos['show_nb_comments'] ?? null;
    }

    public function getLanguage() : string
    {
        return $this->infos['language'] ?? '';
    }

    public function getUserId()
    {
        return $this->infos['user_id'] ?? null;
    }

    public function getLevel()
    {
        return $this->infos['level'] ?? null;
    }

    public function wantExpand()
    {
        return $this->infos['expand'] ?? false;
    }

    /**
     * return an array which will be sent to template to display recent icon
     *
     * @param string $date
     * @param bool $is_child_date
     * @return array
     */
    public function getIcon(\DateTime $date = null, $is_child_date = false)
    {
        if ($date === null) {
            return [];
        }

        $icon = [
            'TITLE' => \Phyxo\Functions\Language::l10n(
                'photos posted during the last %d days',
                $this->getRecentPeriod()
            ),
            'IS_CHILD_DATE' => $is_child_date,
        ];

        $now = new \DateTime('now');
        $sql_recent_date = $now->sub(new \DateInterval(sprintf('P%dD', $this->getRecentPeriod())));

        if ($date > $sql_recent_date) {
            return $icon;
        } else {
            return [];
        }
    }
}
