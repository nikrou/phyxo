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

class ImageCategoryRepository extends BaseCategory
{
    public function countAvailableComments(bool $isAdmin = false)
    {
        $query = 'SELECT COUNT(DISTINCT(com.id)) FROM ' . self::IMAGE_CATEGORY_TABLE . ' AS ic';
        $query .= ' LEFT JOIN ' . self::COMMENTS_TABLE . ' AS com ON ic.image_id = com.image_id';
        $query .= ' WHERE ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'ic.image_id'
            ],
            '',
            true
        );

        if (!$isAdmin()) {
            $query .= ' AND validated = \'' . $conn->boolean_to_db(true) . '\'';
        }

        list($nb_available_comments) = $conn->db_fetch_row($conn->db_query($query));

        return $nb_available_comments;
    }
}
