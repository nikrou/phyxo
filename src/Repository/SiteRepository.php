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

class SiteRepository extends BaseRepository
{
    public function count(string $url) : int
    {
        $query = 'SELECT COUNT(id) AS count FROM ' . self::SITES_TABLE;
        $query .= ' WHERE galleries_url = \'' . $this->conn->db_real_escape_string($url) . '\'';
        list($nb_sites) = $this->conn->db_fetch_row($this->conn->db_query($query));

        return $nb_sites;
    }

    public function addSite(array $datas)
    {
        return $this->conn->single_insert(self::SITES_TABLE, $datas, false);
    }

    public function findById(int $id)
    {
        $query = 'SELECT id, galleries_url FROM ' . self::SITES_TABLE;
        $query .= ' WHERE id = ' . $id;

        return $this->conn->db_query($query);
    }

    public function findAll()
    {
        $query = 'SELECT id, galleries_url FROM ' . self::SITES_TABLE;

        return $this->conn->db_query($query);
    }

    // retrieving the site url : "http://domain.com/gallery/" or simply "./galleries/"
    public function getSiteUrl(int $category_id)
    {
        $query = 'SELECT galleries_url FROM ' . self::SITES_TABLE . ' AS s';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' AS c';
        $query .= ' ON s.id = c.site_id';
        $query .= ' WHERE c.id = ' . $category_id;

        return $this->conn->db_query($query);
    }

    public function deleteSite(int $id)
    {
        $query = 'DELETE FROM ' . self::SITES_TABLE;
        $query .= ' WHERE id = ' . $id;
        $this->conn->db_query($query);
    }
}
