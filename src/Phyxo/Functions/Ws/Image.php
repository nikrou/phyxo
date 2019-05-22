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

namespace Phyxo\Functions\Ws;

use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Ws\NamedStruct;
use Phyxo\Ws\NamedArray;
use App\Repository\TagRepository;
use App\Repository\CommentRepository;
use App\Repository\RateRepository;
use App\Repository\ImageTagRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\BaseRepository;
use Phyxo\Functions\Upload;
use Phyxo\Functions\Utils;
use GuzzleHttp\Client;

class Image
{
    /**
     * API method
     * Adds a comment to an image
     * @param mixed[] $params
     *    @option int image_id
     *    @option string author
     *    @option string content
     *    @option string key
     */
    public static function addComment($params, Server $service)
    {
        $result = (new CategoryRepository($service->getConnection()))->findCommentable($service->getUserMapper()->getUser(), [], $params['image_id']);
        if (!$service->getConnection()->db_num_rows($result)) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid image_id');
        }

        $comm = [
            'author' => trim($params['author']),
            'content' => trim($params['content']),
            'image_id' => $params['image_id'],
        ];

        $infos = [];
        $comment_action = $service->getCommentMapper()->insertUserComment($comm, $params['key'], $infos);

        switch ($comment_action) {
            case 'reject':
                $infos[] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
                return new Error(403, implode("; ", $infos));

            case 'validate':
            case 'moderate':
                $ret = [
                    'id' => $comm['id'],
                    'validation' => $comment_action == 'validate',
                ];
                return ['comment' => new NamedStruct($ret)];
            default:
                return new Error(500, "Unknown comment action " . $comment_action);
        }
    }

    /**
     * API method
     * Returns detailed information for an element
     * @param mixed[] $params
     *    @option int image_id
     *    @option int comments_page
     *    @option int comments_per_page
     */
    public static function getInfo($params, Server $service)
    {
        $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $params['image_id'], $visible_images = true);
        if ($service->getConnection()->db_num_rows($result) == 0) {
            return new Error(404, 'image_id not found');
        }

        $image_row = $service->getConnection()->db_fetch_assoc($result);
        $image_row = array_merge($image_row, \Phyxo\Functions\Ws\Main::stdGetUrls($image_row, $service));

        //-------------------------------------------------------- related categories
        $result = (new CategoryRepository($service->getConnection()))->findRelative($service->getUserMapper()->getUser(), [], $image_row['id']);

        $is_commentable = false;
        $related_categories = [];
        while ($row = $service->getConnection()->db_fetch_assoc($result)) {
            $is_commentable = $service->getConnection()->get_boolean($row['commentable']);
            unset($row['commentable']);

            $row['url'] = \Phyxo\Functions\URL::make_index_url(['category' => $row]);
            $row['page_url'] = \Phyxo\Functions\URL::make_picture_url(
                [
                    'image_id' => $image_row['id'],
                    'image_file' => $image_row['file'],
                    'category' => $row
                ]
            );

            $row['id'] = (int)$row['id'];
            $related_categories[] = $row;
        }
        usort($related_categories, '\Phyxo\Functions\Utils::global_rank_compare');

        if (empty($related_categories)) {
            return new Error(401, 'Access denied');
        }

        //-------------------------------------------------------------- related tags
        $related_tags = $service->getTagMapper()->getCommonTags($service->getUserMapper()->getUser(), [$image_row['id']], -1);
        foreach ($related_tags as $i => $tag) {
            $tag['url'] = \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]);
            $tag['page_url'] = \Phyxo\Functions\URL::make_picture_url(
                [
                    'image_id' => $image_row['id'],
                    'image_file' => $image_row['file'],
                    'tags' => [$tag],
                ]
            );

            unset($tag['counter']);
            $tag['id'] = (int)$tag['id'];
            $related_tags[$i] = $tag;
        }

        //------------------------------------------------------------- related rates
        $rating = [
            'score' => $image_row['rating_score'],
            'count' => 0,
            'average' => null,
        ];
        if (isset($rating['score'])) {
            $result = (new RateRepository($service->getConnection()))->calculateRateSummary($image_row['id']);
            $row = $service->getConnection()->db_fetch_assoc($result);

            $rating['score'] = (float)$rating['score'];
            $rating['average'] = (float)$row['average'];
            $rating['count'] = (int)$row['count'];
        }

        //---------------------------------------------------------- related comments
        $related_comments = [];

        $nb_comments = (new CommentRepository($service->getConnection()))->countByImage($image_row['id'], $service->getUserMapper()->isAdmin());

        if ($nb_comments > 0 && $params['comments_per_page'] > 0) {
            $result = (new CommentRepository($service->getConnection()))->getCommentsByImagePerPage(
                $image_row['id'],
                $params['comments_per_page'],
                $params['comments_per_page'] * $params['comments_page']
            );
            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $row['id'] = (int)$row['id'];
                $related_comments[] = $row;
            }
        }

        $comment_post_data = null;
        if ($is_commentable && (!$service->getUserMapper()->isGuest() || ($service->getUserMapper()->isGuest() && $service->getConf()['comments_forall']))) {
            $comment_post_data['author'] = $service->getUserMapper()->getUser()->getUsername();
            $comment_post_data['key'] = \Phyxo\Functions\Utils::get_ephemeral_key(2, $params['image_id']);
        }

        $ret = $image_row;
        foreach (['id', 'width', 'height', 'hit', 'filesize'] as $k) {
            if (isset($ret[$k])) {
                $ret[$k] = (int)$ret[$k];
            }
        }
        foreach (['path', 'storage_category_id'] as $k) {
            unset($ret[$k]);
        }

        $ret['rates'] = [
            Server::WS_XML_ATTRIBUTES => $rating
        ];
        $ret['categories'] = new NamedArray(
            $related_categories,
            'category',
            ['id', 'url', 'page_url']
        );
        $ret['tags'] = new NamedArray(
            $related_tags,
            'tag',
            \Phyxo\Functions\Ws\Main::stdGetTagXmlAttributes()
        );
        if (isset($comment_post_data)) {
            $ret['comment_post'] = [
                Server::WS_XML_ATTRIBUTES => $comment_post_data
            ];
        }
        $ret['comments_paging'] = new NamedStruct(
            [
                'page' => $params['comments_page'],
                'per_page' => $params['comments_per_page'],
                'count' => count($related_comments),
                'total_count' => $nb_comments,
            ]
        );
        $ret['comments'] = new NamedArray(
            $related_comments,
            'comment',
            ['id', 'date']
        );

        return [
            'image' => new NamedStruct($ret, null, ['name', 'comment'])
        ];
    }

    /**
     * API method
     * Rates an image
     * @param mixed[] $params
     *    @option int image_id
     *    @option float rate
     */
    public static function rate($params, Server $service)
    {
        if (!(new ImageRepository($service->getConnection()))->isImageAuthorized($service->getUserMapper()->getUser(), [], $params['image_id'])) {
            return new Error(404, 'Invalid image_id or access denied');
        }
        $res = $service->getRateMapper()->ratePicture($params['image_id'], (int)$params['rate']);

        if ($res == false) {
            return new Error(403, 'Forbidden or rate not in ' . implode(',', $service->getConf()['rate_items']));
        }

        return $res;
    }

    /**
     * API method
     * Returns a list of elements corresponding to a query search
     * @param mixed[] $params
     *    @option string query
     *    @option int per_page
     *    @option int page
     *    @option string order (optional)
     */
    public static function search($params, Server $service)
    {
        $images = [];
        $where_clauses = \Phyxo\Functions\Ws\Main::stdImageSqlFilter($params, 'i.');
        $order_by = \Phyxo\Functions\Ws\Main::stdImageSqlOrder($params, 'i.', $service);

        $super_order_by = false;
        if (!empty($order_by)) {
            $service->getConf()['order_by'] = 'ORDER BY ' . $order_by;
            $super_order_by = true; // quick_search_result might be faster
        }

        $search_result = \Phyxo\Functions\Search::get_quick_search_results(
            $params['query'],
            [
                'super_order_by' => $super_order_by,
                'images_where' => implode(' AND ', $where_clauses)
            ]
        );

        $image_ids = array_slice(
            $search_result['items'],
            $params['page'] * $params['per_page'],
            $params['per_page']
        );

        if (count($image_ids)) {
            $result = (new ImageRepository($service->getConnection()))->findByIds($image_ids);
            $image_ids = array_flip($image_ids);
            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $image = [];
                foreach (['id', 'width', 'height', 'hit'] as $k) {
                    if (isset($row[$k])) {
                        $image[$k] = (int)$row[$k];
                    }
                }
                foreach (['file', 'name', 'comment', 'date_creation', 'date_available'] as $k) {
                    $image[$k] = $row[$k];
                }

                $image = array_merge($image, \Phyxo\Functions\Ws\Main::stdGetUrls($row, $service));
                $images[$image_ids[$image['id']]] = $image;
            }
            ksort($images, SORT_NUMERIC);
            $images = array_values($images);
        }

        return [
            'paging' => new NamedStruct(
                [
                    'page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'count' => count($images),
                    'total_count' => count($search_result['items']),
                ]
            ),
            'images' => new NamedArray(
                $images,
                'image',
                \Phyxo\Functions\Ws\Main::stdGetImageXmlAttributes()
            )
        ];
    }

    /**
     * API method
     * Sets the level of an image
     * @param mixed[] $params
     *    @option int image_id
     *    @option int level
     */
    public static function setPrivacyLevel($params, Server $service)
    {
        if (!in_array($params['level'], $service->getConf()['available_permission_levels'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
        }

        $result = (new ImageRepository($service->getConnection()))->updateImages(['level' => (int)$params['level']], $params['image_id']);
        if ($affected_rows = $service->getConnection()->db_changes($result)) {
            $service->getUserMapper()->invalidateUserCache();
        }

        return $affected_rows;
    }

    /**
     * API method
     * Sets the rank of an image in a category
     * @param mixed[] $params
     *    @option int image_id
     *    @option int category_id
     *    @option int rank
     */
    public static function setRank($params, Server $service)
    {
        // does the image really exist?
        if (!(new ImageRepository($service->getConnection()))->isImageExists($params['image_id'])) {
            return new Error(404, 'image_id not found');
        }

        // is the image associated to this category?
        if (!(new ImageCategoryRepository($service->getConnection()))->isImageAssociatedToCategory($params['image_id'], $params['category_id'])) {
            return new Error(404, 'This image is not associated to this category');
        }

        // what is the current higher rank for this category?
        if ($max_rank = (new ImageCategoryRepository($service->getConnection()))->maxRankForCategory($params['category_id'])) {
            if ($params['rank'] > $max_rank) {
                $params['rank'] = $max_rank + 1;
            }
        } else {
            $params['rank'] = 1;
        }

        // update rank for all other photos in the same category
        (new ImageCategoryRepository($service->getConnection()))->updateRankForCategory($params['rank'], $params['category_id']);

        // set the new rank for the photo
        (new ImageCategoryRepository($service->getConnection()))->updateRankForImage($params['rank'], $params['image_id'], $params['category_id']);

        // return data for client
        return [
            'image_id' => $params['image_id'],
            'category_id' => $params['category_id'],
            'rank' => $params['rank'],
        ];
    }

    /**
     * API method
     * Adds a file chunk
     * @param mixed[] $params
     *    @option string data
     *    @option string original_sum
     *    @option string type = 'file'
     *    @option int position
     */
    public static function addChunk($params, Server $service)
    {
        foreach ($params as $param_key => $param_value) {
            if ('data' == $param_key) {
                continue;
            }
        }

        $upload_dir = $service->getConf()['upload_dir'] . '/buffer';

        // create the upload directory tree if not exists
        if (!\Phyxo\Functions\Utils::mkgetdir($upload_dir, \Phyxo\Functions\Utils::MKGETDIR_DEFAULT & ~\Phyxo\Functions\Utils::MKGETDIR_DIE_ON_ERROR)) {
            return new Error(500, 'error during buffer directory creation');
        }

        $filename = sprintf(
            '%s-%s-%05u.block',
            $params['original_sum'],
            $params['type'],
            $params['position']
        );

        $bytes_written = file_put_contents(
            $upload_dir . '/' . $filename,
            base64_decode($params['data'])
        );

        if (false === $bytes_written) {
            return new Error(
                500,
                'an error has occured while writting chunk ' . $params['position'] . ' for ' . $params['type']
            );
        }
    }

    /**
     * API method
     * Adds a file
     * @param mixed[] $params
     *    @option int image_id
     *    @option string type = 'file'
     *    @option string sum
     */
    public static function addFile($params, Server $service)
    {
        // what is the path and other infos about the photo?
        $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $params['image_id']);

        if ($service->getConnection()->db_num_rows($result) == 0) {
            return new \Phyxo\Ws\Error(404, "image_id not found");
        }

        $image = $service->getConnection()->db_fetch_assoc($result);

        // we do not take the imported "thumb" into account
        if ('thumb' == $params['type']) {
            self::remove_chunks($image['md5sum'], $params['type'], $service);
            return true;
        }

        // we only care about the "original"
        $original_type = 'file';
        if ('high' == $params['type']) {
            $original_type = 'high';
        }

        $file_path = $service->getConf()['upload_dir'] . '/buffer/' . $image['md5sum'] . '-original';

        self::merge_chunks($file_path, $image['md5sum'], $original_type, $service);
        chmod($file_path, 0644);

        // if we receive the "file", we only update the original if the "file" is bigger than current original
        if ('file' == $params['type']) {
            $do_update = false;

            $infos = self::image_infos($file_path);

            foreach (['width', 'height', 'filesize'] as $image_info) {
                if ($infos[$image_info] > $image[$image_info]) {
                    $do_update = true;
                }
            }

            if (!$do_update) {
                unlink($file_path);
                return true;
            }
        }

        $image_id = self::addUploadedFile(
            $service,
            $file_path,
            $image['file'],
            [],
            null,
            $params['image_id'],
            $image['md5sum'] // we force the md5sum to remain the same
        );
        $service->getTagMapper()->sync_metadata([$image_id]);
    }

    /**
     * API method
     * Adds an image
     * @param mixed[] $params
     *    @option string original_sum
     *    @option string original_filename (optional)
     *    @option string name (optional)
     *    @option string author (optional)
     *    @option string date_creation (optional)
     *    @option string comment (optional)
     *    @option string categories (optional) - "cat_id[,rank];cat_id[,rank]"
     *    @option string tags_ids (optional) - "tag_id,tag_id"
     *    @option int level
     *    @option bool check_uniqueness
     *    @option int image_id (optional)
     */
    public static function add($params, Server $service)
    {
        if ($params['image_id'] > 0) {
            if (!(new ImageRepository($service->getConnection()))->isImageExists($params['image_id'])) {
                return new Error(404, 'image_id not found');
            }
        }

        // does the image already exists ?
        if ($params['check_uniqueness']) {
            if ('md5sum' == $service->getConf()['uniqueness_mode']) {
                $result = (new ImageRepository($service->getConnection()))->findByField('md5sum', $params['original_sum']);
            } elseif ('filename' == $service->getConf()['uniqueness_mode']) {
                $result = (new ImageRepository($service->getConnection()))->findByField('file', $params['original_filename']);
            }
            $row = $service->getConnection()->db_fetch_row($result);
            if (empty($row)) {
                return new Error(500, 'file already exists');
            }
        }

        // we only take the biggest photos sent on addChunk. If "high" is available we use it as "original" else we use "file".
        self::remove_chunks($params['original_sum'], 'thumb', $service);

        if (isset($params['high_sum'])) {
            $original_type = 'high';
            self::remove_chunks($params['original_sum'], 'file', $service);
        } else {
            $original_type = 'file';
        }

        $file_path = $service->getConf()['upload_dir'] . '/buffer/' . $params['original_sum'] . '-original';

        self::merge_chunks($file_path, $params['original_sum'], $original_type, $service);
        chmod($file_path, 0644);

        $image_id = self::addUploadedFile(
            $service,
            $file_path,
            $params['original_filename'],
            [], // categories
            isset($params['level']) ? $params['level'] : null,
            $params['image_id'] > 0 ? $params['image_id'] : null,
            $params['original_sum']
        );
        $service->getTagMapper()->sync_metadata([$image_id]);

        $info_columns = [
            'name',
            'author',
            'comment',
            'date_creation',
        ];

        $update = [];
        foreach ($info_columns as $key) {
            if (isset($params[$key])) {
                $update[$key] = $params[$key];
            }
        }

        if (count(array_keys($update)) > 0) {
            (new ImageRepository($service->getConnection()))->updateImage($update, $image_id);
        }

        $url_params = ['image_id' => $image_id];

        // let's add links between the image and the categories
        if (isset($params['categories'])) {
            self::addImageCategoryRelations($image_id, $params['categories'], $replace_mode = false, $service);

            if (preg_match('/^\d+/', $params['categories'], $matches)) {
                $category_id = $matches[0];

                $result = (new CategoryRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $category_id);
                $category = $service->getConnection()->db_fetch_assoc($result);
                $url_params['section'] = 'categories';
                $url_params['category'] = $category;
            }
        }

        // and now, let's create tag associations
        if (!empty($params['tag_ids'])) {
            $service->getTagMapper()->setTags(explode(',', $params['tag_ids']), $image_id);
        }

        $service->getUserMapper()->invalidateUserCache();

        return [
            'image_id' => $image_id,
            'url' => \Phyxo\Functions\URL::make_picture_url($url_params),
        ];
    }

    /**
     * API method
     * Adds a image (simple way)
     * @param mixed[] $params
     *    @option int[] category
     *    @option string name (optional)
     *    @option string author (optional)
     *    @option string comment (optional)
     *    @option int level
     *    @option string|string[] tags
     *    @option int image_id (optional)
     */
    public static function addSimple($params, Server $service)
    {
        if (!isset($_FILES['image'])) {
            return new Error(405, 'The image (file) is missing');
        }

        if ($params['image_id'] > 0) {
            $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $params['image_id']);
            list($count) = $service->getConnection()->db_fetch_row($result);
            if ($count == 0) {
                return new Error(404, 'image_id not found');
            }
        }

        $image_id = self::addUploadedFile(
            $service,
            $_FILES['image']['tmp_name'],
            $_FILES['image']['name'],
            $params['category'],
            8,
            $params['image_id'] > 0 ? $params['image_id'] : null
        );
        $service->getTagMapper()->sync_metadata([$image_id]);

        $info_columns = [
            'name',
            'author',
            'comment',
            'level',
            'date_creation',
        ];

        $update = [];
        foreach ($info_columns as $key) {
            if (isset($params[$key])) {
                $update[$key] = $params[$key];
            }
        }

        (new ImageRepository($service->getConnection()))->updateImage($update, $image_id);

        if (!empty($params['tags'])) {
            $tag_ids = [];
            if (is_array($params['tags'])) {
                foreach ($params['tags'] as $tag_name) {
                    $tag_ids[] = $service->getTag['tags']->tagIdFromTagName($tag_name);
                }
            } else {
                $tag_names = preg_split('~(?<!\\\),~', $params['tags']);
                foreach ($tag_names as $tag_name) {
                    $tag_ids[] = $service->getTagMapper()->tagIdFromTagName(preg_replace('#\\\\*,#', ',', $tag_name));
                }
            }

            $service->getTagMapper()->addTags($tag_ids, [$image_id]);
        }

        $url_params = ['image_id' => $image_id];

        if (!empty($params['category'])) {
            $result = (new CategoryRepository($service->getConnection()))->findById($params['category'][0]);
            $category = $service->getConnection()->db_fetch_assoc($result);
            $url_params['section'] = 'categories';
            $url_params['category'] = $category;
        }

        return [
            'image_id' => $image_id,
            'url' => \Phyxo\Functions\URL::make_picture_url($url_params),
        ];
    }

    /**
     * API method
     * Adds a image (simple way)
     * @param mixed[] $params
     *    @option int[] category
     *    @option string name (optional)
     *    @option string author (optional)
     *    @option string comment (optional)
     *    @option int level
     *    @option string|string[] tags
     *    @option int image_id (optional)
     */
    public static function upload($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $upload_dir = __DIR__ . '/../../../../' . $service->getConf()['upload_dir'] . '/buffer';

        // create the upload directory tree if not exists
        if (!\Phyxo\Functions\Utils::mkgetdir($upload_dir, \Phyxo\Functions\Utils::MKGETDIR_DEFAULT & ~\Phyxo\Functions\Utils::MKGETDIR_DIE_ON_ERROR)) {
            return new Error(500, 'error during buffer directory creation');
        }

        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }

        $filePath = $upload_dir . DIRECTORY_SEPARATOR . $fileName;

        // Chunking might be enabled
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

        // Open temp file
        if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        // Check if file has been uploaded
        if (!$chunks || $chunk == $chunks - 1) {
            // Strip the temp .part suffix off
            rename("{$filePath}.part", $filePath);

            $image_id = self::addUploadedFile(
                $service,
                $filePath,
                $params['name'],
                $params['category'],
                $params['level'],
                null // image_id = not provided, this is a new photo
            );
            $service->getTagMapper()->sync_metadata([$image_id]);

            $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $image_id);
            $image_infos = $service->getConnection()->db_fetch_assoc($result);

            $result = (new ImageCategoryRepository($service->getConnection()))->countByCategory($params['category'][0]);
            list(, $nb_photos) = $service->getConnection()->db_fetch_row($result);
            $category_name = $service->getCategoryMapper()->getCatDisplayNameFromId($params['category'][0]);

            return [
                'image_id' => $image_id,
                'src' => $service->getRouter()->generate('media', \Phyxo\Image\DerivativeImage::relativeThumbInfos($image_infos, $service->getConf()['picture_ext'])),
                'name' => $image_infos['name'],
                'category' => [
                    'id' => $params['category'][0],
                    'nb_photos' => $nb_photos,
                    'label' => $category_name,
                ]
            ];
        }
    }

    /**
     * API method
     * Check if an image exists by it's name or md5 sum
     * @param mixed[] $params
     *    @option string md5sum_list (optional)
     *    @option string filename_list (optional)
     */
    public static function exist($params, Server $service)
    {
        $split_pattern = '/[\s,;\|]/';
        $res = [];

        if ('md5sum' == $service->getConf()['uniqueness_mode']) {
            if (empty($params['md5sum_list'])) {
                return [];
            }

            // search among photos the list of photos already added, based on md5sum list
            $md5sums = preg_split(
                $split_pattern,
                $params['md5sum_list'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            $result = (new ImageRepository($service->getConnection()))->findByFields('md5sum', $md5sums);
            $id_of_md5 = $service->getConnection()->result2array($result, 'md5sum', 'id');

            foreach ($md5sums as $md5sum) {
                $res[$md5sum] = null;
                if (isset($id_of_md5[$md5sum])) {
                    $res[$md5sum] = $id_of_md5[$md5sum];
                }
            }
        } elseif ('filename' == $service->getConf()['uniqueness_mode']) {
            if (empty($params['filename_list'])) {
                return [];
            }

            // search among photos the list of photos already added, based on filename list
            $filenames = preg_split(
                $split_pattern,
                $params['filename_list'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            $result = (new ImageRepository($service->getConnection()))->findByFields('file', $filenames);
            $id_of_filename = $service->getConnection()->result2array($result, 'file', 'id');

            foreach ($filenames as $filename) {
                $res[$filename] = null;
                if (isset($id_of_filename[$filename])) {
                    $res[$filename] = $id_of_filename[$filename];
                }
            }
        }

        return $res;
    }

    /**
     * API method
     * Check is file has been update
     * @param mixed[] $params
     *    @option int image_id
     *    @option string file_sum
     */
    public static function checkFiles($params, Server $service)
    {
        $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $params['image_id']);

        if ($service->getConnection()->db_num_rows($result) == 0) {
            return new Error(404, 'image_id not found');
        }

        list($path) = $service->getConnection()->db_fetch_row($result);

        $ret = [];

        if (isset($params['thumbnail_sum'])) {
            // We always say the thumbnail is equal to create no reaction on the other side.
            $ret['thumbnail'] = 'equals';
        }

        if (isset($params['high_sum'])) {
            $ret['file'] = 'equals';
            $compare_type = 'high';
        } elseif (isset($params['file_sum'])) {
            $compare_type = 'file';
        }

        if (isset($compare_type)) {
            if (md5_file($path) != $params[$compare_type . '_sum']) {
                $ret[$compare_type] = 'differs';
            } else {
                $ret[$compare_type] = 'equals';
            }
        }

        return $ret;
    }

    /**
     * API method
     * Set list of related tags of an image
     * @param mixed[] $params
     *    @option bool sort_by_counter
     */
    public static function setRelatedTags($params, Server $service)
    {
        if (!$service->isPost()) {
            return new Error(405, "This method requires HTTP POST");
        }

        // @TODO : add voters
        if (empty($service->getConf()['tags_permission_add'])) {
            // || !$service->getUserMapper()->isAuthorizeStatus($services['users']->getAccessTypeStatus($service->getConf()['tags_permission_add']))) && (empty($service->getConf()['tags_permission_delete'])
            // || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($service->getConf()['tags_permission_delete'])))) {
            return new Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to add nor delete tags'));
        }

        $message = '';
        if (empty($params['tags'])) {
            $params['tags'] = [];
        }

        $result = (new TagRepository($service->getConnection()))->getTagsByImage($params['image_id']);
        $removed_tags_ids = $new_tags_ids = [];
        $current_tags_ids = $service->getConnection()->result2array($result, null, 'id');
        $current_tags = array_map(function ($id) {
            return '~~' . $id . '~~';
        }, $current_tags_ids);
        $removed_tags = array_diff($current_tags, $params['tags']);
        $new_tags = array_diff($params['tags'], $current_tags);

        if (count($removed_tags) > 0) {
            // @TODO : add voters
            if (empty($service->getConf()['tags_permission_delete'])) {
                // || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($service->getConf()['tags_permission_delete']))) {
                return new Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to delete tags'));
            }
        }
        if (count($new_tags) > 0) {
            // @TODO : add voters
            if (empty($service->getConf()['tags_permission_add'])) {
                // || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($service->getConf()['tags_permission_add']))) {
                return new Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to add tags'));
            }
        }

        try {
            if (empty($params['tags'])) { // remove all tags for an image
                if (isset($service->getConf()['delete_tags_immediately']) && $service->getConf()['delete_tags_immediately'] == 0) {
                    $service->getTagMapper()->toBeValidatedTags(
                        $current_tags_ids,
                        $params['image_id'],
                        ['status' => 0, 'user_id' => $service->getUserMapper()->getUser->getId()]
                    );
                } else {
                    (new ImageTagRepository($service->getConnection()))->deleteBy('image_id', $params['image_id']);
                }
            } else {
                // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is 1
                if (count($removed_tags) > 0) {
                    $removed_tags_ids = $service->getTagMapper()->getTagsIds($removed_tags);
                    if (isset($service->getConf()['delete_tags_immediately']) && $service->getConf()['delete_tags_immediately'] == 0) {
                        $service->getTagMapper()->toBeValidatedTags(
                            $removed_tags_ids,
                            $params['image_id'],
                            ['status' => 0, 'user_id' => $service->getUserMapper()->getUser()->getId()]
                        );
                    } else {
                        $service->getTagMapper()->dissociateTags($removed_tags_ids, $params['image_id']);
                    }
                }

                if (count($new_tags) > 0) {
                    $new_tags_ids = $service->getTagMapper()->getTagsIds($new_tags);
                    if (isset($service->getConf()['publish_tags_immediately']) && $service->getConf()['publish_tags_immediately'] == 0) {
                        $service->getTagMapper()->toBeValidatedTags(
                            $new_tags_ids,
                            $params['image_id'],
                            ['status' => 1, 'user_id' => $service->getUserMapper()->getUser()->getId()]
                        );
                    } else {
                        $service->getTagMapper()->associateTags($new_tags_ids, $params['image_id']);
                    }
                }
            }
        } catch (\Exception $e) {
            return new Error(500, '[ws_images_setRelatedTags]  Something went wrong when updating tags');
        }
    }

    /**
     * API method
     * Sets details of an image
     * @param mixed[] $params
     *    @option int image_id
     *    @option string file (optional)
     *    @option string name (optional)
     *    @option string author (optional)
     *    @option string date_creation (optional)
     *    @option string comment (optional)
     *    @option string categories (optional) - "cat_id[,rank];cat_id[,rank]"
     *    @option string tags_ids (optional) - "tag_id,tag_id"
     *    @option int level (optional)
     *    @option string single_value_mode
     *    @option string multiple_value_mode
     */
    public static function setInfo($params, Server $service)
    {
        $result = (new ImageRepository($service->getConnection()))->findById($service->getUserMapper()->getUser(), [], $params['image_id']);

        if ($service->getConnection()->db_num_rows($result) == 0) {
            return new Error(404, 'image_id not found');
        }

        $image_row = $service->getConnection()->db_fetch_assoc($result);

        // database registration
        $update = [];

        $info_columns = [
            'name',
            'author',
            'comment',
            'level',
            'date_creation',
        ];

        foreach ($info_columns as $key) {
            if (isset($params[$key])) {
                if ('fill_if_empty' == $params['single_value_mode']) {
                    if (empty($image_row[$key])) {
                        $update[$key] = $params[$key];
                    }
                } elseif ('replace' == $params['single_value_mode']) {
                    $update[$key] = $params[$key];
                } else {
                    return new Error(
                        500,
                        '[ws_images_setInfo]'
                            . ' invalid parameter single_value_mode "' . $params['single_value_mode'] . '"'
                            . ', possible values are {fill_if_empty, replace}.'
                    );
                }
            }
        }

        if (isset($params['file'])) {
            if (!empty($image_row['storage_category_id'])) {
                return new Error(
                    500,
                    '[ws_images_setInfo] updating "file" is forbidden on photos added by synchronization'
                );
            }

            $update['file'] = $params['file'];
        }

        if (count(array_keys($update)) > 0) {
            $update['id'] = $params['image_id'];

            (new ImageRepository($service->getConnection()))->updateImage($update, $update['id']);
        }

        if (isset($params['categories'])) {
            self::addImageCategoryRelations(
                $params['image_id'],
                $params['categories'],
                ('replace' == $params['multiple_value_mode'] ? true : false),
                $service
            );
        }

        // and now, let's create tag associations
        if (isset($params['tag_ids'])) {
            $tag_ids = [];

            foreach (explode(',', $params['tag_ids']) as $candidate) {
                $candidate = trim($candidate);

                if (preg_match(PATTERN_ID, $candidate)) {
                    $tag_ids[] = $candidate;
                }
            }

            if ('replace' == $params['multiple_value_mode']) {
                $service->getT['tags']->setTags($tag_ids, $params['image_id']);
            } elseif ('append' == $params['multiple_value_mode']) {
                $service->getTagMapper()->addTags($tag_ids, [$params['image_id']]);
            } else {
                return new Error(
                    500,
                    '[ws_images_setInfo]'
                        . ' invalid parameter multiple_value_mode "' . $params['multiple_value_mode'] . '"'
                        . ', possible values are {replace, append}.'
                );
            }
        }

        $service->getUserMapper()->invalidateUserCache();
    }

    /**
     * API method
     * Deletes an image
     * @param mixed[] $params
     *    @option int|int[] image_id
     *    @option string pwg_token
     */
    public static function delete($params, Server $service)
    {
        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        // @TODO: simplify !!!
        if (!is_array($params['image_id'])) {
            $params['image_id'] = preg_split(
                '/[\s,;\|]/',
                $params['image_id'],
                -1,
                PREG_SPLIT_NO_EMPTY
            );
        }
        $params['image_id'] = array_map('intval', $params['image_id']);

        $image_ids = [];
        foreach ($params['image_id'] as $image_id) {
            if ($image_id > 0) {
                $image_ids[] = $image_id;
            }
        }

        $number_of_elements_deleted = \Phyxo\Functions\Utils::delete_elements($image_ids, true);
        $service->getUserMapper()->invalidateUserCache();

        return $number_of_elements_deleted;
    }

    /**
     * API method
     * Checks if Piwigo is ready for upload
     * @param mixed[] $params
     */
    public static function checkUpload($params, Server $service)
    {
        $ret['message'] = \Phyxo\Functions\Utils::ready_for_upload_message();
        $ret['ready_for_upload'] = true;
        if (!empty($ret['message'])) {
            $ret['ready_for_upload'] = false;
        }

        return $ret;
    }

    // protected methods, not part of the API

    /**
     * Sets associations of an image
     * @param int $image_id
     * @param string $categories_string - "cat_id[,rank];cat_id[,rank]"
     * @param bool $replace_mode - removes old associations
     */
    protected static function addImageCategoryRelations($image_id, $categories_string, $replace_mode = false, Server $service)
    {
        /* let's add links between the image and the categories
         *
         * $params['categories'] should look like 123,12;456,auto;789 which means:
         *
         * 1. associate with category 123 on rank 12
         * 2. associate with category 456 on automatic rank
         * 3. associate with category 789 on automatic rank
         */
        $cat_ids = [];
        $rank_on_category = [];
        $search_current_ranks = false;

        $tokens = explode(';', $categories_string);
        foreach ($tokens as $token) {
            @list($cat_id, $rank) = explode(',', $token);

            if (!preg_match('/^\d+$/', $cat_id)) {
                continue;
            }

            $cat_ids[] = $cat_id;

            if (!isset($rank)) {
                $rank = 'auto';
            }
            $rank_on_category[$cat_id] = $rank;

            if ($rank == 'auto') {
                $search_current_ranks = true;
            }
        }

        $cat_ids = array_unique($cat_ids);

        if (count($cat_ids) == 0) {
            return new Error(
                500,
                '[\Phyxo\Functions\Ws\Images::addImageCategoryRelations] there is no category defined in "' . $categories_string . '"'
            );
        }

        $result = (new CategoryRepository($service->getConnection()))->findByIds($cat_ids);
        $db_cat_ids = $service->getConnection()->result2array($result, null, 'id');

        $unknown_cat_ids = array_diff($cat_ids, $db_cat_ids);
        if (count($unknown_cat_ids) != 0) {
            return new Error(
                500,
                '[\Phyxo\Functions\Ws\Images::addImageCategoryRelations] the following categories are unknown: ' . implode(', ', $unknown_cat_ids)
            );
        }

        $to_update_cat_ids = [];

        // in case of replace mode, we first check the existing associations
        $result = (new ImageCategoryRepository($service->getConnection()))->findByImageId($image_id);
        $existing_cat_ids = $service->getConnection()->result2array($result, null, 'category_id');

        if ($replace_mode) {
            $to_remove_cat_ids = array_diff($existing_cat_ids, $cat_ids);
            if (count($to_remove_cat_ids) > 0) {
                (new ImageCategoryRepository($service->getConnection()))->deleteByCategory($to_remove_cat_ids, [$image_id]);
                $service->getCategoryMapper()->updateCategory($to_remove_cat_ids);
            }
        }

        $new_cat_ids = array_diff($cat_ids, $existing_cat_ids);
        if (count($new_cat_ids) == 0) {
            return true;
        }

        if ($search_current_ranks) {
            $result = (new ImageCategoryRepository($service->getConnection()))->findMaxRankForEachCategories($new_cat_ids);
            $current_rank_of = $service->getConnection()->result2array($result, 'category_id', 'max_rank');

            foreach ($new_cat_ids as $cat_id) {
                if (!isset($current_rank_of[$cat_id])) {
                    $current_rank_of[$cat_id] = 0;
                }

                if ('auto' == $rank_on_category[$cat_id]) {
                    $rank_on_category[$cat_id] = $current_rank_of[$cat_id] + 1;
                }
            }
        }

        $inserts = [];

        foreach ($new_cat_ids as $cat_id) {
            $inserts[] = [
                'image_id' => $image_id,
                'category_id' => $cat_id,
                'rank' => $rank_on_category[$cat_id],
            ];
        }

        (new ImageCategoryRepository($service->getConnection()))->insertImageCategories(array_keys($inserts[0]), $inserts);

        $service->getCategoryMapper()->updateCategory($new_cat_ids);
    }

    /**
     * Merge chunks added by pwg.images.addChunk
     * @param string $output_filepath
     * @param string $original_sum
     * @param string $type
     */
    protected static function merge_chunks(string $output_filepath, string $original_sum, string $type, Server $service)
    {
        if (is_file($output_filepath)) {
            unlink($output_filepath);

            if (is_file($output_filepath)) {
                return new Error(500, '[merge_chunks] error while trying to remove existing ' . $output_filepath);
            }
        }

        $upload_dir = $service->getConf()['upload_dir'] . '/buffer';
        $pattern = '/' . $original_sum . '-' . $type . '/';
        $chunks = [];

        if ($handle = opendir($upload_dir)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match($pattern, $file)) {
                    $chunks[] = $upload_dir . '/' . $file;
                }
            }
            closedir($handle);
        }

        sort($chunks);

        $i = 0;

        foreach ($chunks as $chunk) {
            $string = file_get_contents($chunk);

            if (!file_put_contents($output_filepath, $string, FILE_APPEND)) {
                return new Error(500, '[merge_chunks] error while writting chunks for ' . $output_filepath);
            }

            unlink($chunk);
        }
    }

    /**
     * Deletes chunks added with pwg.images.addChunk
     * @param string $original_sum
     * @param string $type
     *
     * Function introduced for Piwigo 2.4 and the new "multiple size"
     * (derivatives) feature. As we only need the biggest sent photo as
     * "original", we remove chunks for smaller sizes. We can't make it earlier
     * in addChunk because at this moment we don't know which $type
     * will be the biggest (we could remove the thumb, but let's use the same
     * algorithm)
     */
    protected static function remove_chunks(string $original_sum, string $type, Server $service)
    {
        $upload_dir = $service->getConf()['upload_dir'] . '/buffer';
        $pattern = '/' . $original_sum . '-' . $type . '/';
        $chunks = [];

        if ($handle = opendir($upload_dir)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match($pattern, $file)) {
                    $chunks[] = $upload_dir . '/' . $file;
                }
            }
            closedir($handle);
        }

        foreach ($chunks as $chunk) {
            unlink($chunk);
        }
    }

    protected static function addUploadedFile(Server $service, string $source_filepath, string $original_filename = '', array $categories = [], $level = null, $image_id = null, $original_md5sum = null): int
    {
        // 1) move uploaded file to upload/2010/01/22/20100122003814-449ada00.jpg
        //
        // 2) keep/resize original
        //
        // 3) register in database

        // TODO
        // * check md5sum (already exists?)
        if (isset($original_md5sum)) {
            $md5sum = $original_md5sum;
        } else {
            $md5sum = md5_file($source_filepath);
        }

        $file_path = null;
        $is_tiff = false;

        if (isset($image_id)) { // this photo already exists, we update it
            $result = (new ImageRepository($service->getConnection()))->findByField('id', $image_id);
            while ($row = $service->getConnection()->db_fetch_assoc($result)) {
                $file_path = $row['path'];
            }

            if (!isset($file_path)) {
                throw new \Exception('[' . __FUNCTION__ . '] this photo does not exist in the database');
            }

            // delete all physical files related to the photo (thumbnail, web site, HD)
            \Phyxo\Functions\Utils::delete_element_files([$image_id]);
        } else {
            // this photo is new current date. @TODO: really need a query for that ?
            $now = (new BaseRepository($service->getConnection()))->getNow();
            list($year, $month, $day) = preg_split('/[^\d]/', $now, 4);

            $upload_dir = realpath(__DIR__ . '/../../../../' . $service->getConf()['upload_dir']);

            // upload directory hierarchy
            $filename_dir = sprintf('%s/%s/%s/%s', $upload_dir, $year, $month, $day);

            // compute file path
            $date_string = preg_replace('/[^\d]/', '', $now);
            $random_string = substr($md5sum, 0, 8);
            $filename_wo_ext = $date_string . '-' . $random_string;
            $file_path = $filename_dir . '/' . $filename_wo_ext . '.';

            list($width, $height, $type) = getimagesize($source_filepath);

            if (IMAGETYPE_PNG == $type) {
                $file_path .= 'png';
            } elseif (IMAGETYPE_GIF == $type) {
                $file_path .= 'gif';
            } elseif (IMAGETYPE_TIFF_MM == $type or IMAGETYPE_TIFF_II == $type) {
                $is_tiff = true;
                $file_path .= 'tif';
            } elseif (IMAGETYPE_JPEG == $type) {
                $file_path .= 'jpg';
            } elseif (isset($service->getConf()['upload_form_all_types']) && $service->getConf()['upload_form_all_types']) {
                $original_extension = strtolower(\Phyxo\Functions\Utils::get_extension($original_filename));

                if (in_array($original_extension, $service->getConf()['file_ext'])) {
                    $file_path .= $original_extension;
                } else {
                    throw new \Exception('unexpected file type');
                }
            } else {
                throw new \Exception('forbidden file type');
            }

            Utils::prepare_directory($filename_dir);
        }

        if (is_uploaded_file($source_filepath)) {
            move_uploaded_file($source_filepath, $file_path);
        } else {
            rename($source_filepath, $file_path);
        }
        @chmod($file_path, 0644);

        if ($is_tiff && \Phyxo\Image\Image::get_library() === 'ExtImagick') {
            // move the uploaded file to pwg_representative sub-directory
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = $service->getConf()['tiff_representative_ext'];
            $representative_file_path .= $representative_ext;

            Utils::prepare_directory(dirname($representative_file_path));

            $exec = $service->getConf()['ext_imagick_dir'] . 'convert';

            if ('jpg' == $service->getConf()['tiff_representative_ext']) {
                $exec .= ' -quality 98';
            }

            $exec .= ' "' . realpath($file_path) . '"';

            $dest = pathinfo($representative_file_path);
            $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '"';

            $exec .= ' 2>&1';
            @exec($exec, $returnarray);

            // sometimes ImageMagick creates file-0.jpg (full size) + file-1.jpg
            // (thumbnail). I don't know how to avoid it.
            $representative_file_abspath = realpath($dest['dirname']) . '/' . $dest['basename'];
            if (!file_exists($representative_file_abspath)) {
                $first_file_abspath = preg_replace(
                    '/\.' . $representative_ext . '$/',
                    '-0.' . $representative_ext,
                    $representative_file_abspath
                );

                if (file_exists($first_file_abspath)) {
                    rename($first_file_abspath, $representative_file_abspath);
                }
            }
        }

        // generate pwg_representative in case of video
        $ffmpeg_video_exts = [ // extensions tested with FFmpeg
            'wmv', 'mov', 'mkv', 'mp4', 'mpg', 'flv', 'asf', 'xvid', 'divx', 'mpeg',
            'avi', 'rm',
        ];

        if (isset($original_extension) && in_array($original_extension, $ffmpeg_video_exts)) {
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = 'jpg';
            $representative_file_path .= $representative_ext;

            Utils::prepare_directory(dirname($representative_file_path));

            $second = 1;

            $ffmpeg = $service->getConf()['ffmpeg_dir'] . 'ffmpeg';
            $ffmpeg .= ' -i "' . $file_path . '"';
            $ffmpeg .= ' -an -ss ' . $second;
            $ffmpeg .= ' -t 1 -r 1 -y -vcodec mjpeg -f mjpeg';
            $ffmpeg .= ' "' . $representative_file_path . '"';
            @exec($ffmpeg);

            if (!file_exists($representative_file_path)) {
                $representative_ext = null;
            }
        }

        if (isset($original_extension) && 'pdf' == $original_extension && \Phyxo\Image\Image::get_library() === 'ExtImagick') {
            $representative_file_path = dirname($file_path) . '/pwg_representative/';
            $representative_file_path .= \Phyxo\Functions\Utils::get_filename_wo_extension(basename($file_path)) . '.';

            $representative_ext = 'jpg';
            $representative_file_path .= $representative_ext;

            Utils::prepare_directory(dirname($representative_file_path));

            $exec = $service->getConf()['ext_imagick_dir'] . 'convert';
            $exec .= ' -quality 98';
            $exec .= ' "' . realpath($file_path) . '"[0]';

            $dest = pathinfo($representative_file_path);
            $exec .= ' "' . realpath($dest['dirname']) . '/' . $dest['basename'] . '"';
            $exec .= ' 2>&1';
            @exec($exec, $returnarray);
        }

        if (\Phyxo\Image\Image::get_library() !== 'GD') {
            if ($service->getConf()['original_resize']) {
                if (Utils::need_resize($file_path, $service->getConf()['original_resize_maxwidth'], $service->getConf()['original_resize_maxheight'])) {
                    $img = new \Phyxo\Image\Image($file_path);

                    $img->pwg_resize(
                        $file_path,
                        $service->getConf()['original_resize_maxwidth'],
                        $service->getConf()['original_resize_maxheight'],
                        $service->getConf()['original_resize_quality'],
                        $service->getConf()['upload_form_automatic_rotation'],
                        false
                    );

                    $img->destroy();
                }
            }
        }

        // we need to save the rotation angle in the database to compute
        // width/height of "multisizes"
        $rotation_angle = \Phyxo\Image\Image::get_rotation_angle($file_path);
        $rotation = \Phyxo\Image\Image::get_rotation_code_from_angle($rotation_angle);

        $file_infos = self::image_infos($file_path);

        if (isset($image_id)) {
            $update = [
                'file' => $service->getConnection()->db_real_escape_string(isset($original_filename) ? $original_filename : basename($file_path)),
                'filesize' => $file_infos['filesize'],
                'width' => $file_infos['width'],
                'height' => $file_infos['height'],
                'md5sum' => $md5sum,
                'added_by' => $service->getUserMapper()->getUser()->getId(),
                'rotation' => $rotation,
            ];

            if (isset($level)) {
                $update['level'] = $level;
            }

            (new ImageRepository($service->getConnection()))->updateImage($update, $image_id);
        } else {
            $file = $service->getConnection()->db_real_escape_string(isset($original_filename) ? $original_filename : basename($file_path));
            $insert = [
                'file' => $file,
                'name' => \Phyxo\Functions\Utils::get_name_from_file($file),
                'date_available' => $now,
                'path' => preg_replace('#^' . preg_quote(dirname($upload_dir)) . '#', '.', realpath($file_path)),
                'filesize' => $file_infos['filesize'],
                'width' => $file_infos['width'],
                'height' => $file_infos['height'],
                'md5sum' => $md5sum,
                'added_by' => $service->getUserMapper()->getUser()->getId(),
                'rotation' => $rotation,
            ];

            if (isset($level)) {
                $insert['level'] = $level;
            }

            if (isset($representative_ext)) {
                $insert['representative_ext'] = $representative_ext;
            }

            $image_id = (new ImageRepository($service->getConnection()))->addImage($insert);
        }

        if (isset($categories) && count($categories) > 0) {
            $service->getCategoryMapper()->associateImagesToCategories(
                [$image_id],
                $categories
            );
        }

        // update metadata from the uploaded file (exif/iptc)
        if ($service->getConf()['use_exif'] && !function_exists('exif_read_data')) {
            $service->getConf()['use_exif'] = false;
        }

        $service->getUserMapper()->invalidateUserCache();

        // cache thumbnail
        $result = (new ImageRepository($service->getConnection()))->findByField('id', (string) $image_id);
        $image_infos = $service->getConnection()->db_fetch_assoc($result);

        \Phyxo\Functions\URL::set_make_full_url();

        if ($service->getConnection()->getLayer() === 'mysql') {
            $conf_derivatives = @unserialize(stripslashes($service->getConf()['derivatives']));
        } else {
            $conf_derivatives = @unserialize($service->getConf()['derivatives']);
        }
        \Phyxo\Image\ImageStdParams::load_from_db($conf_derivatives);

        $thumb_url = \Phyxo\Image\DerivativeImage::thumb_url($image_infos, $service->getConf()['picture_ext']);
        \Phyxo\Functions\URL::unset_make_full_url();

        // force cache generation
        $client = new Client(['http_errors' => false]);
        $client->request('GET', $thumb_url);

        return $image_id;
    }

    protected static function image_infos($path)
    {
        list($width, $height) = getimagesize($path);
        $filesize = floor(filesize($path) / 1024);

        return [
            'width' => $width,
            'height' => $height,
            'filesize' => $filesize,
        ];
    }
}
