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

use Symfony\Component\Security\Core\User\UserInterface;


class FavoriteRepository extends BaseRepository
{
    public function findAll(int $user_id)
    {
        $query = 'SELECT image_id FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function addFavorite(int $user_id, int $image_id)
    {
        $this->conn->single_insert(
            self::FAVORITES_TABLE,
            [
                'user_id' => $user_id,
                'image_id' => $image_id
            ],
            $auto_increment_for_table = false
        );
    }

    public function deleteFavorite(int $user_id, int $image_id)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $query .= ' AND image_id = ' . $image_id;
        $this->conn->db_query($query);
    }

    public function deleteImagesFromFavorite(array $ids, ? int $user_id = null)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE image_id ' . $this->conn->in($ids);

        if (!is_null($user_id)) {
            $query .= ' AND user_id = ' . $user_id;
        }
        $this->conn->db_query($query);
    }

    public function removeAllFavorites(int $user_id)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function isFavorite(int $user_id, int $image_id) : bool
    {
        $query = 'SELECT COUNT(1) AS nb_fav FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= ' AND user_id = ' . $user_id;
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return ($row['nb_fav'] != 0);
    }

    public function findAuthorizedImagesInFavorite(UserInterface $user, array $filter = [])
    {
        $query = 'SELECT DISTINCT f.image_id FROM ' . self::FAVORITES_TABLE . ' AS f';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON f.image_id = ic.image_id';
        $query .= ' WHERE f.user_id = ' . $user->getId();
        $query .= ' ' . $this->getSQLConditionFandF($user, $filter, ['forbidden_categories' => 'ic.category_id'], ' AND ');

        return $this->conn->db_query($query);
    }

    /**
     * Deletes favorites of the current user if he's not allowed to see them.
     */
    public function deleteUnauthorizedImagesFromFavorites(UserInterface $user, array $filter = [])
    {
        // $filter['visible_categories'] and $filter['visible_images']
        // must be not used because filter <> restriction
        // retrieving images allowed : belonging to at least one authorized category
        $result = $this->findAuthorizedImagesInFavorite($user, $filter);
        $authorizeds = $this->conn->result2array($result, null, 'image_id');

        $result = $this->findAll($user->getId());
        $favorites = $this->conn->result2array($result, null, 'image_id');

        $to_deletes = array_diff($favorites, $authorizeds);
        if (count($to_deletes) > 0) {
            (new FavoriteRepository($this->conn))->deleteImagesFromFavorite($to_deletes, $user->getId());
        }
    }
}
