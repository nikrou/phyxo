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

use Phyxo\DBLayer\DBLayer;

class BaseRepository
{
    const USERS_TABLE = 'phyxo_users';

    const TAGS_TABLE = 'phyxo_tags';
    const IMAGE_TAG_TABLE = 'phyxo_image_tag';

    const IMAGES_TABLE = 'phyxo_images';
    const IMAGE_CATEGORY_TABLE = 'phyxo_image_category';

    const COMMENTS_TABLE = 'phyxo_comments';
    const USER_CACHE_TABLE = 'phyxo_user_cache';
    const USER_CACHE_CATEGORIES_TABLE = 'phyxo_user_cache_categories';

    protected $conn = null;

    public function __construct(DBLayer $conn)
    {
        $this->conn = $conn;
    }
}
