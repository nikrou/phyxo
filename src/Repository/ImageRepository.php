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


class ImageRepository extends BaseRepository
{
    public function count() : int
    {
        $query = 'SELECT COUNT(1) FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);
        list($nb_images) = $this->conn->db_fetch_row($result);

        return $nb_images;
    }

    public function addImage(array $datas): int
    {
        return $this->conn->single_insert(self::IMAGES_TABLE, $datas);
    }

    public function findAll(? string $order_by = null)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;

        if (!is_null($order_by)) {
            $query .= ' ' . $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function findGroupByAuthor(UserInterface $user, array $filter = [])
    {
        $query = 'SELECT author, id FROM ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = i.id';
        $query .= ' ' . $this->getSQLConditionFandF(
            $user,
            $filter,
            [
                'forbidden_categories' => 'category_id',
                'visible_categories' => 'category_id',
                'visible_images' => 'id'
            ],
            ' WHERE '
        );
        $query .= ' AND author IS NOT NULL';
        $query .= ' GROUP BY author, id';
        $query .= ' ORDER BY author;';

        return $this->conn->db_query($query);
    }

    public function findById(UserInterface $user, array $filter = [], int $image_id, ? bool $visible_images = null)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id =' . $image_id;

        if (!is_null($visible_images)) {
            $query .= ' ' . $this->getSQLConditionFandF($user, $filter, ['visible_images' => 'id'], ' AND ');
        }

        return $this->conn->db_query($query);
    }

    public function findImagesInVirtualCategory(array $image_ids, int $category_id)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE id ' . $this->conn->in($image_ids);
        $query .= ' AND category_id = ' . $category_id;
        $query .= ' AND (category_id != storage_category_id OR storage_category_id IS NULL);';

        return $this->conn->db_query($query);
    }

    public function findVirtualCategoriesWithImages(array $image_ids)
    {
        $query = 'SELECT DISTINCT(category_id) AS id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE id ' . $this->conn->in($image_ids);
        $query .= ' AND (category_id != storage_category_id OR storage_category_id IS NULL);';

        return $this->conn->db_query($query);
    }

    public function findVisibleImages(array $category_ids = [], string $recent_period)
    {
        $query = 'SELECT distinct image_id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE ';
        $query .= ' date_available >= ' . $this->conn->db_get_recent_period_expression($recent_period);

        if (count($category_ids) > 0) {
            $query .= ' AND category_id  ' . $this->conn->in($category_ids);
        }

        return $this->conn->db_query($query);
    }

    public function findDuplicates(array $fields)
    {
        $query = 'SELECT ' . $this->conn->db_group_concat('id') . ' AS ids FROM ' . self::IMAGES_TABLE;
        $query .= ' GROUP BY ' . implode(', ', $fields);
        $query .= ' HAVING COUNT(*) > 1';

        return $this->conn->db_query($query);
    }

    public function getImagesFromCategories(array $where, string $order_by, int $limit, int $offset = 0)
    {
        $query = 'SELECT i.id, i.file, i.date_available, i.date_creation, i.name, i.comment, i.author, i.hit, i.filesize,';
        $query .= ' i.width, i.height, i.coi, i.representative_ext, i.date_metadata_update, i.rating_score, i.path,';
        $query .= ' i.storage_category_id, i.level, i.md5sum, i.added_by, i.rotation, i.latitude, i.longitude, i.lastmodified,';
        $query .= ' ' . $this->conn->db_group_concat('category_id') . ' AS cat_ids FROM ' . self::IMAGES_TABLE . ' i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON i.id = image_id';
        $query .= ' WHERE ' . implode(' AND ', $where);
        $query .= ' GROUP BY i.id';
        $query .= ' ' . $order_by;
        $query .= ' LIMIT ' . $limit;
        $query .= ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function findByIds(array $image_ids)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($image_ids);

        return $this->conn->db_query($query);
    }

    public function findWithConditions(array $where, ? int $start_id = null, ? int $limit = null, string $order = 'ORDER BY id DESC')
    {
        $query = 'SELECT id, path, representative_ext, width, height, rotation FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . implode(' AND ', $where);

        if (!is_null($start_id)) {
            $query .= ' AND id < ' . $start_id;
        }

        $query .= ' ' . $order;

        if (!is_null($limit)) {
            $query .= ' LIMIT ' . $limit;
        }

        return $this->conn->db_query($query);
    }

    public function findAllWidthAndHeight()
    {
        $query = 'SELECT DISTINCT width, height FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE width IS NOT NULL AND height IS NOT NULL';

        return $this->conn->db_query($query);
    }

    public function findFilesize()
    {
        $query = 'SELECT filesize FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE filesize IS NOT NULL GROUP BY filesize';

        return $this->conn->db_query($query);
    }

    public function searchDistinctId(string $field, array $where, bool $permissions, string $order_by, ? int $limit = null)
    {
        $where = array_filter($where, function ($w) {
            return !empty($w);
        });

        $query = 'SELECT DISTINCT(' . $field . '),' . $this->addOrderByFields($order_by) . ' FROM ' . self::IMAGES_TABLE . ' AS i';
        if ($permissions) {
            $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ' . $order_by;

        if (!is_null($limit)) {
            $query .= ' LIMIT ' . $limit;
        }

        return $this->conn->db_query($query);
    }

    public function findByImageIdsAndCategoryId(array $image_ids, ? int $category_id = null, string $order_by, int $limit, int $offset = 0)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE id ' . $this->conn->in($image_ids);

        if (!is_null($category_id)) {
            $query .= ' AND category_id = ' . $category_id;
        }
        $query .= ' ' . $order_by;
        $query .= ' LIMIT ' . $limit;
        $query .= ' OFFSET ' . $offset;

        return $this->conn->db_query($query);
    }

    public function findList(array $ids, string $forbidden, string $order_by)
    {
        $query = 'SELECT DISTINCT(id),' . $this->addOrderByFields($order_by) . ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $forbidden;
        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    public function getReferenceDateForCategories(string $field, string $minmax, array $category_ids)
    {
        $query = 'SELECT category_id,' . $minmax . '(' . $field . ') as ref_date FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE category_id ' . $this->conn->in($category_ids);
        $query .= ' GROUP BY category_id';

        return $this->conn->db_query($query);
    }

    public function qsearchImages(array $where)
    {
        $query = 'SELECT id from ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' WHERE ';
        $query .= '(' . implode(' OR ', $where) . ')';

        return $this->conn->db_query($query);
    }

    public function isImageAuthorized(UserInterface $user, array $filter = [], int $image_id) : bool
    {
        $query = 'SELECT DISTINCT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE id=' . $image_id;
        $query .= ' ' . $this->getSQLConditionFandF(
            $user,
            $filter,
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'id',
            ],
            ' AND '
        );
        $result = $this->conn->db_query($query);

        return ($this->conn->db_num_rows($result) === 1);
    }

    public function isImageExists(int $image_id) : bool
    {
        $query = 'SELECT DISTINCT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id=' . $image_id;
        $result = $this->conn->db_query($query);

        return $this->conn->db_num_rows($result) === 1;
    }

    public function updateImage(array $fields, int $id)
    {
        $this->conn->single_update(self::IMAGES_TABLE, $fields, ['id' => $id]);
    }

    public function updateImageHitAndLastModified(int $id)
    {
        $query = 'UPDATE ' . self::IMAGES_TABLE;
        $query .= ' SET hit = hit+1, lastmodified = lastmodified';
        $query .= ' WHERE id = ' . $id;
        $this->conn->db_query($query);
    }

    public function updateImages(array $fields, array $ids)
    {
        $is_first = true;

        $query = 'UPDATE ' . self::IMAGES_TABLE;
        $query .= ' SET ';
        foreach ($fields as $key => $value) {
            $separator = $is_first ? '' : ', ';

            if (is_bool($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->boolean_to_db($value) . '\'';
            } elseif (isset($value)) {
                $query .= $separator . $key . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';
            } else {
                $query .= $separator . $key . ' = NULL';
            }
            $is_first = false;
        }
        $query .= ' WHERE id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    /**
     * this list does not contain images that are not in at least an authorized category
     */
    public function getForbiddenImages(array $forbidden_categories = [], int $level = 0)
    {
        $query = 'SELECT DISTINCT(id) FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE level > ' . $level;
        if (count($forbidden_categories) > 0) {
            $query .= ' AND category_id NOT ' . $this->conn->in($forbidden_categories);
        }

        return $this->conn->db_query($query);
    }

    public function getImagesFromCaddie(array $image_ids, int $user_id)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::CADDIE_TABLE;
        $query .= ' ON id = element_id AND user_id=' . $user_id;
        $query .= ' WHERE id ' . $this->conn->in($image_ids);
        $query .= ' AND element_id IS NULL';

        return $this->conn->db_query($query);
    }

    public function findFirstDate()
    {
        $query = 'SELECT MIN(date_available) FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);

        list($first_date) = $this->conn->db_fetch_row($result);

        return $first_date;
    }

    public function findImageWithNoTag()
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_TAG_TABLE . ' ON id = image_id';
        $query .= ' WHERE tag_id is null';

        return $this->conn->db_query($query);
    }

    public function findImageWithNoAlbum()
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE category_id is null';

        return $this->conn->db_query($query);
    }

    public function findByFields(string $field, array $values, ? string $order_by = null)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . $this->conn->in($values);

        if (!is_null($order_by)) {
            $query .= $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function findByField(string $field, string $value, ? string $order_by = null)
    {
        $query = 'SELECT id, path, rotation, coi FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . ' = \'' . $this->conn->db_real_escape_string($value) . '\'';

        if (!is_null($order_by)) {
            $query .= $order_by;
        }

        return $this->conn->db_query($query);
    }

    public function filterByField(string $field, string $operator = '=', string $value, ? string $order_by = null)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE level ' . $operator . '\'' . $this->conn->db_real_escape_string($value) . '\'';
        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    public function searchByField($field, $value)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE ' . $field . ' LIKE \'' . $this->conn->db_real_escape_string($value) . '\'';

        $this->conn->db_query($query);
    }

    public function getNextId()
    {
        return $this->conn->db_nextval('id', self::IMAGES_TABLE);
    }

    public function findBestRated(int $limit)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' ORDER by rating_score DESC LIMIT ' . $limit;

        $this->conn->db_query($query);
    }

    public function findWithNoStorageOrStorageCategoryId(array $categories)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE (';
        $query .= 'storage_category_id IS NULL';
        if (count($categories) > 0) {
            $query .= ' OR storage_category_id NOT ' . $this->conn->in($categories);
        }
        $query .= ')';

        return $this->conn->db_query($query);
    }

    public function findByStorageCategoryId(array $cat_ids, bool $only_new)
    {
        $query = 'SELECT id, path, representative_ext FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE storage_category_id ' . $this->conn->in($cat_ids);
        if ($only_new) {
            $query .= ' AND date_metadata_update IS NULL';
        }

        return $this->conn->db_query($query);
    }

    public function findDistinctStorageId()
    {
        $query = 'SELECT DISTINCT(storage_category_id) FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE storage_category_id IS NOT NULL';

        return $this->conn->db_query($query);
    }

    public function findImagesInCategory(int $category_id, string $order_by)
    {
        $query = 'SELECT id, file, date_available, date_creation, name, comment, author, hit, filesize,';
        $query .= ' width, height, coi, representative_ext, date_metadata_update, rating_score, path,';
        $query .= ' storage_category_id, level, md5sum, added_by, rotation, latitude, longitude, lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE category_id = ' . $category_id;
        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    public function getImagesInfosInCategory(int $category_id)
    {
        $query = 'SELECT COUNT(image_id), MIN(DATE(date_available)), MAX(DATE(date_available)) FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON image_id = id';
        $query .= ' WHERE category_id = ' . $category_id;

        return $this->conn->db_query($query);
    }

    public function getRecentImages(string $where_sql, $date_available, int $limit)
    {
        $query = 'SELECT DISTINCT c.uppercats, COUNT(DISTINCT i.id) AS img_count FROM ' . self::IMAGES_TABLE . ' i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON i.id = image_id';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' c ON c.id = category_id';
        $query .= ' ' . $where_sql;
        $query .= ' AND date_available = \'' . $this->conn->db_real_escape_string($date_available) . '\'';
        $query .= ' GROUP BY category_id, c.uppercats ORDER BY img_count DESC LIMIT ' . $limit;

        return $this->conn->db_query($query);
    }

    public function getRecentPostedImages(string $where_sql, int $limit)
    {
        $query = 'SELECT date_available, COUNT(DISTINCT id) AS nb_elements,';
        $query .= ' COUNT(DISTINCT category_id) AS nb_cats FROM ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON id = image_id';
        $query .= ' ' . $where_sql;
        $query .= ' GROUP BY date_available ORDER BY date_available DESC';
        $query .= ' LIMIT ' . $limit;

        return $this->conn->db_query($query);
    }

    public function findRandomImages(string $where_sql, string $date_available = '', int $limit)
    {
        $query = 'SELECT i.id, i.file, i.date_available, i.date_creation, i.name, i.comment, i.author, i.hit, i.filesize,';
        $query .= ' i.width, i.height, i.coi, i.representative_ext, i.date_metadata_update, i.rating_score, i.path,';
        $query .= ' i.storage_category_id, i.level, i.md5sum, i.added_by, i.rotation, i.latitude, i.longitude, i.lastmodified';
        $query .= ' FROM ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON id = image_id';
        $query .= ' ' . $where_sql;
        if ($date_available !== '') {
            $query .= ' AND date_available=\'' . $this->conn->db_real_escape_string($date_available) . '\'';
        }
        $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '() LIMIT ' . $limit;

        return $this->conn->db_query($query);
    }

    public function getNewElements(UserInterface $user, array $filter = [], ? string $start = null, ? string $end = null, bool $count_only = false)
    {
        if ($count_only) {
            $query = 'SELECT count(1) ';
        } else {
            $query = 'SELECT image_id ';
        }
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON image_id = id';
        $query .= ' WHERE';

        if (!empty($start)) {
            $query .= ' date_available > \'' . $this->conn->db_real_escape_string($start) . '\'';
        }

        if (!empty($end)) {
            if (!is_null($start)) {
                $query .= ' AND';
            }
            $query .= ' date_available <= \'' . $this->conn->db_real_escape_string($end) . '\'';
        }

        $query .= ' ' . $this->getStandardSQLWhereRestrictFilter($user, $filter, ' AND ', 'id');

        if ($count_only) {
            list($nb_images) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_images;
        } else {
            return $this->conn->db_query($query);
        }
    }

    public function getUpdatedCategories(UserInterface $user, array $filter = [], ? string $start = null, ? string $end = null, bool $count_only = false)
    {
        if ($count_only) {
            $query = 'SELECT count(1) ';
        } else {
            $query = 'SELECT category_id';
        }
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON image_id = id';
        $query .= ' WHERE';

        if (!empty($start)) {
            $query .= ' date_available > \'' . $this->conn->db_real_escape_string($start) . '\'';
        }

        if (!empty($end)) {
            if (!is_null($start)) {
                $query .= ' AND';
            }
            $query .= ' date_available <= \'' . $this->conn->db_real_escape_string($end) . '\'';
        }

        $query .= ' ' . $this->getStandardSQLWhereRestrictFilter($user, $filter, ' AND ', 'id');

        if ($count_only) {
            list($nb_categories) = $this->conn->db_fetch_row($this->conn->db_query($query));

            return $nb_categories;
        } else {
            return $this->conn->db_query($query);
        }
    }

    public function updatePathByStorageId(string $path, int $cat_id)
    {
        $query = 'UPDATE ' . self::IMAGES_TABLE;
        $query .= ' SET path = ' . $this->conn->db_concat(["'" . $path . "/'", 'file']);
        $query .= ' WHERE storage_category_id = ' . $cat_id;
        $this->conn->db_query($query);
    }

    public function getFavorites(UserInterface $user, array $filter = [], string $order_by)
    {
        $query = 'SELECT image_id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::FAVORITES_TABLE . ' ON image_id = id';
        $query .= ' WHERE user_id = ' . $user->getId();
        $query .= ' ' . $this->getSQLConditionFandF($user, $filter, ['visible_images' => 'id'], 'AND');
        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    public function findCategoryWithLastImageAdded()
    {
        $query = 'SELECT category_id FROM ' . self::IMAGES_TABLE . ' AS i';
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' AS ic ON image_id = i.id';
        $query .= ' LEFT JOIN ' . self::CATEGORIES_TABLE . ' AS c ON category_id = c.id';
        $query .= ' ORDER BY i.id DESC LIMIT 1';

        return $this->conn->db_query($query);
    }

    public function findMaxIdAndCount()
    {
        $query = 'SELECT MAX(id)+1, COUNT(1) FROM ' . self::IMAGES_TABLE;

        return $this->conn->db_query($query);
    }

    public function findMaxDateAvailable() : string
    {
        $query = 'SELECT MAX(date_available) AS max_date FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        if (empty($row['max_date'])) {
            return '';
        } else {
            return $row['max_date'];
        }
    }

    public function findMinDateAvailable() : string
    {
        $query = 'SELECT MIN(date_available) AS min_date FROM ' . self::IMAGES_TABLE;
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return $row['min_date'];
    }

    public function findImagesFromLastImport(string $max_date)
    {
        $query = 'SELECT id FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE date_available BETWEEN ';
        $query .= $this->conn->db_get_recent_period_expression(1, $max_date) . ' AND \'' . $max_date . '\'';

        return $this->conn->db_query($query);
    }

    public function findOrphanImages()
    {
        $query = 'SELECT image_id FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id WHERE id IS NULL;';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findImagesInPeriods(string $level, string $date_where = '', string $condition, array $category_ids = [])
    {
        $query = 'SELECT DISTINCT(' . $level . ') as period,';
        $query .= ' COUNT(DISTINCT id) as nb_images';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $date_where . ' GROUP BY period';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findImagesInPeriodsByIds(string $level, array $ids = [], string $date_where = '')
    {
        $query = 'SELECT DISTINCT(' . $level . ') as period,';
        $query .= ' COUNT(DISTINCT id) as nb_images';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $date_where . ' GROUP BY period';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findNextPrevPeriodByIds(array $ids = [], array $date_elements, array $calendar_levels, string $date_field = '')
    {
        $sub_queries = [];
        $nb_elements = count($date_elements);
        for ($i = 0; $i < $nb_elements; $i++) {
            if ($date_elements[$i] !== 'any') { // @TODO: replace by null ?
                $sub_queries[] = $this->conn->db_cast_to_text($calendar_levels[$i]['sql']);
            }
        }

        $query = 'SELECT ' . $this->conn->db_concat_ws($sub_queries, '-') . ' AS period';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' AND ' . $date_field . ' IS NOT NULL GROUP BY period';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findNextPrevPeriod(array $date_elements, array $calendar_levels, string $date_field = '', string $condition, array $category_ids = [])
    {
        $sub_queries = [];
        $nb_elements = count($date_elements);
        for ($i = 0; $i < $nb_elements; $i++) {
            if ($date_elements[$i] !== 'any') { // @TODO: replace by null ?
                $sub_queries[] = $this->conn->db_cast_to_text($calendar_levels[$i]['sql']);
            }
        }

        $query = 'SELECT ' . $this->conn->db_concat_ws($sub_queries, '-') . ' AS period';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' AND ' . $date_field . ' IS NOT NULL GROUP BY period';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findDistincIds(string $condition, array $category_ids = [], string $order_by)
    {
        $query = 'SELECT DISTINCT id,' . $this->addOrderByFields($order_by);
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $order_by;

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findDayOfMonthPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $query = 'SELECT ' . $this->conn->db_get_dayofmonth($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findDayOfMonthPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        $query = 'SELECT ' . $this->conn->db_get_dayofmonth($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findYYYYMMPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $query = 'SELECT ' . $this->conn->db_get_date_YYYYMM($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findYYYYMMPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        $query = 'SELECT ' . $this->conn->db_get_date_YYYYMM($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period, ' . $date_field;
        $query .= ' ORDER BY ' . $this->conn->db_get_year($date_field) . ' DESC, ' . $this->conn->db_get_month($date_field) . ' ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findMMDDPeriodAndImagesCountByIds(string $date_field, string $date_where = '', array $ids)
    {
        $query = 'SELECT ' . $this->conn->db_get_date_MMDD($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findMMDDPeriodAndImagesCount(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        $query = 'SELECT ' . $this->conn->db_get_date_MMDD($date_field) . ' as period,';
        $query .= ' COUNT(distinct id) as count';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $date_where;
        $query .= ' GROUP BY period';
        $query .= ' ORDER BY period ASC';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findOneRandomInWeekByIds(string $date_field, string $date_where = '', array $ids)
    {
        $query = 'SELECT id, file, representative_ext, path, width, height, rotation, ';
        $query .= $this->conn->db_get_dayofweek($date_field) . '-1 as dow';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $query .= ' ' . $date_where;
        $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '() LIMIT 1';

        return $this->conn->db_query($query);
    }

    // calendar query
    public function findOneRandomInWeek(string $date_field, string $date_where = '', string $condition, array $category_ids = [])
    {
        $query = 'SELECT id, file, representative_ext, path, width, height, rotation, ';
        $query .= $this->conn->db_get_dayofweek($date_field) . '-1 as dow';
        $query .= ' FROM ' . self::IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . self::IMAGE_CATEGORY_TABLE . ' ON id = image_id';
        $query .= ' WHERE ' . (!empty($condition) ? $condition : '1 = 1');
        if (!empty($category_ids)) {
            $query .= ' AND category_id ' . $this->conn->in($category_ids);
        }
        $query .= ' ' . $date_where;
        $query .= ' ORDER BY ' . $this->conn::RANDOM_FUNCTION . '() LIMIT 1';

        return $this->conn->db_query($query);
    }

    public function deleteByElementIds(array $ids)
    {
        $query = 'DELETE FROM ' . self::IMAGES_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);
        $this->conn->db_query($query);
    }

    public function massUpdates(array $fields, array $datas)
    {
        $this->conn->mass_updates(self::IMAGES_TABLE, $fields, $datas);
    }

    public function addImages(array $fields, array $datas)
    {
        $this->conn->mass_inserts(self::IMAGES_TABLE, $fields, $datas);
    }

    public function getMaxLastModified()
    {
        $query = 'SELECT ' . $this->conn->db_date_to_ts('MAX(lastmodified)') . ', COUNT(1)';
        $query .= ' FROM ' . self::IMAGES_TABLE;

        return $this->conn->db_query($query);
    }
}
