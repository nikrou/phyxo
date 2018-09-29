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

class RateRepository extends BaseRepository
{
    public function count(? int $element_id = null) : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::RATE_TABLE;
        if (!is_null($element_id)) {
            $query .= ' WHERE element_id = ' . $element_id;
        }

        list($nb_rates) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_rates;
    }

    public function countImagesRatedForUser(string $user_filter = '') : int
    {
        $query = 'SELECT COUNT(DISTINCT(r.element_id)) FROM ' . self::RATE_TABLE . ' AS r';
        if (!empty($user_filter)) {
            $query .= ' WHERE ' . $user_filter;
        }
        list($nb_images) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_images;
    }

    public function findAll()
    {
        $query = 'SELECT user_id, element_id, anonymous_id, rate, date FROM ' . self::RATE_TABLE;
        $query .= ' ORDER by date DESC';

        return $this->conn->db_query($query);
    }

    public function findByUserIdAndElementIdAndAnonymousId(int $user_id, int $element_id, ? string $anonymous_id = null)
    {
        $query = 'SELECT rate FROM ' . self::RATE_TABLE;
        $query .= ' WHERE element_id = ' . $element_id;
        $query .= ' AND user_id = ' . $user_id;

        if (!is_null($anonymous_id)) {
            $query .= ' AND anonymous_id = \'' . $this->conn->db_real_escape_string($anonymous_id) . '\'';
        }

        return $this->conn->db_query($query);
    }

    public function calculateAverageyElement()
    {
        $query = 'SELECT element_id, AVG(rate) AS avg FROM ' . self::RATE_TABLE;
        $query .= ' GROUP BY element_id';

        return $this->conn->db_query($query);
    }

    public function calculateRateSummary(int $element_id) : array
    {
        $query = 'SELECT COUNT(rate) AS count, ROUND(AVG(rate),2) AS average FROM ' . self::RATE_TABLE;
        $query .= ' WHERE element_id = ' . $element_id;

        $result = $this->conn->db_query($query);

        return $this->conn->db_fetch_assoc();
    }

    public function findByUserAndAnonymousId(int $user_id, string $anonymous_id)
    {
        $query = 'SELECT element_id FROM ' . self::RATE_TABLE;
        $query .= ' WHERE user_id = ' . $user['id'];
        $query .= ' AND anonymous_id = \'' . $this->conn->db_real_escape_string($anonymous_id) . '\'';

        return $this->conn->db_query($query);
    }

    public function findByElementId(int $element_id)
    {
        $query = 'SELECT user_id, element_id, anonymous_id, rate, date FROM ' . self::RATE_TABLE . ' AS r';
        $query .= ' WHERE r.element_id=' . $element_id;
        $query .= ' ORDER BY date DESC';

        return $this->conn->db_query($query);
    }

    public function addRate(int $user_id, int $element_id, string $anonymous_id, int $rate, \DateTime $date)
    {
        return $this->conn->single_insert(
            [
                'user_id' => $user_id,
                'element_id' => $element_id,
                'anonymous_id' => $anonymous_id,
                'rate' => $rate,
                'date' => $date
            ]
        );
    }

    public function deleteRates(int $user_id, string $anonymous_id, array $elements)
    {
        $query = 'DELETE FROM ' . self::RATE_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $query .= ' AND anonymous_id = \'' . $this->conn->db_real_escape_string($anonymous_id) . '\'';
        $query .= ' AND element_id ' . $this->conn->in($elements);
        $this->conn->db_query($query);
    }

    public function deleteRate(int $user_id, ? int $element_id = null, ? string $anonymous_id = null)
    {
        $query = 'DELETE FROM ' . self::RATE_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        if (!is_null($element_id)) {
            $query .= ' AND element_id = ' . $element_id;
        }

        if (!is_null($anonymous_id)) {
            $query .= ' AND anonymous_id = \'' . $this->conn->db_real_escape_string($anonymous_id) . '\'';
        }

        $result = $this->conn->db_query($query);

        return $this->conn->db_changes($result);
    }

    public function deleteByElementIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::RATE_TABLE;
        $query .= ' WHERE element_id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function updateRate(array $datas, array $where)
    {
        $this->single_update(self::RATE_TABLE, $datas, $where);
    }

    public function calculateRateByElement()
    {
        $query = 'SELECT element_id, COUNT(rate) AS rcount, SUM(rate) AS rsum FROM ' . self::RATE_TABLE;
        $query .= ' GROUP by element_id';

        return $this->conn->db_query($query);
    }

    public function getRatePerImage(string $user_filter, string $order, int $limit, int $offet = 0)
    {
        $query = 'SELECT i.id, i.path, i.file, i.representative_ext, i.rating_score AS score,';
        $query .= 'MAX(r.date) AS recently_rated, ROUND(AVG(r.rate), 2) AS avg_rates,';
        $query .= 'COUNT(r.rate) AS nb_rates, SUM(r.rate) AS sum_rates FROM ' . self::RATE_TABLE . ' AS r';
        $query .= ' LEFT JOIN ' . self::IMAGES_TABLE . ' AS i ON r.element_id = i.id';
        if (!empty($user_filter)) {
            $query .= ' WHERE ' . $user_filter;
        }
        $query .= ' GROUP BY i.id, i.path, i.file, i.representative_ext, i.rating_score, r.element_id';
        $query .= ' ORDER BY ' . $order;
        $query .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->db_query($query);
    }
}
