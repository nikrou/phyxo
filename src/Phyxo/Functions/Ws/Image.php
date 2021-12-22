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

use App\Entity\Comment;
use App\Entity\Image as EntityImage;
use App\Entity\ImageAlbum;
use App\Entity\ImageTag;
use App\Entity\Rate;
use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use App\Security\TagVoter;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageOptimizer;
use Phyxo\Image\ImageStandardParams;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

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
        if (!$service->getImageMapper()->getRepository()->isAuthorizedToUser($service->getUserMapper()->getUser()->getUserInfos()->getForbiddenCategories(), $params['image_id'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid image_id or access denied');
        }

        $comm = [
            'author' => trim($params['author']),
            'content' => trim($params['content']),
            'image_id' => $params['image_id'],
            'ip' => $service->getRequest()->getClientIp()
        ];

        $infos = [];
        $comment_action = $service->getCommentMapper()->insertUserComment($comm, $infos);

        switch ($comment_action) {
            case 'reject':
                $infos[] = 'Your comment has NOT been registered because it did not pass the validation rules';
                return new Error(403, implode("; ", $infos));

            case 'validate':
            case 'moderate':
                $ret = [
                    /** @phpstan-ignore-next-line Offset 'id' does not exist on array - $comm is modify by insertUserComment */
                    'id' => $comm['id'],
                    'validation' => $comment_action == 'validate',
                ];
                return ['comment' => $ret];
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
        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);
        if (is_null($image)) {
            return new Error(404, 'image_id not found');
        }

        //-------------------------------------------------------- related categories
        $is_commentable = false;
        $related_categories = [];
        foreach ($service->getAlbumMapper()->getRepository()->findRelative($image->getId(), $service->getUserMapper()->getUser()->getUserInfos()->getForbiddenCategories()) as $album) {
            $is_commentable = $album->isCommentable();
            $album_infos = array_merge(
                $album->toArray(),
                [
                    'url' => $service->getRouter()->generate('album', ['category_id' => $album->getId()]),
                    'page_url' => $service->getRouter()->generate('picture', ['image_id' => $params['image_id'], 'type' => 'category', 'element_id' => $album->getId()])
                ]
            );

            $related_categories[] = $album_infos;
        }
        usort($related_categories, '\Phyxo\Functions\Utils::global_rank_compare');

        if (count($related_categories) === 0) {
            return new Error(401, 'Access denied');
        }

        //-------------------------------------------------------------- related tags
        $related_tags = $service->getTagMapper()->getCommonTags($service->getUserMapper()->getUser(), [$image->getId()], -1);
        foreach ($related_tags as $i => $tag) {
            $tag['url'] = $service->getRouter()->generate('images_by_tags', ['tag_ids' => Utils::tagToUrl($tag)]);
            $tag['page_url'] = $service->getRouter()->generate('picture', ['image_id' => $image->getId(), 'type' => 'tags', 'element_id' => Utils::tagToUrl($tag)]);

            unset($tag['counter']);
            $tag['id'] = (int)$tag['id'];
            $related_tags[$i] = $tag;
        }

        //------------------------------------------------------------- related rates
        $rating = [
            'score' => $image->getRatingScore(),
            'count' => 0,
            'average' => null,
        ];

        if (is_null($rating['score'])) {
            $rate_summary = $service->getManagerRegistry()->getRepository(Rate::class)->calculateRateSummary($image->getId());

            $rating['score'] = (float)$rating['score'];
            $rating['average'] = round($rate_summary['average'], 2);
            $rating['count'] = (int)$rate_summary['count'];
        }

        //---------------------------------------------------------- related comments
        $related_comments = [];

        $nb_comments = $service->getManagerRegistry()->getRepository(Comment::class)->countForImage($image->getId(), $service->getUserMapper()->isAdmin());
        if ($nb_comments > 0 && $params['comments_per_page'] > 0) {
            foreach ($service->getManagerRegistry()->getRepository(Comment::class)->getCommentsForImagePerPage(
                $image->getId(),
                $params['comments_per_page'],
                $params['comments_per_page'] * $params['comments_page']
            ) as $comment) {
                $related_comments[] = [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'author' => $comment->getAuthor(),
                    'website_url' => $comment->getWebsiteUrl(),
                    'author_id' => $comment->getUser()->getId(),
                    'image_id' => $comment->getImage()->getId(),
                    'validated' => $comment->isValidated(),
                ];
            }
        }

        $comment_post_data = null;
        /** @phpstan-ignore-next-line */
        if ($is_commentable && (!$service->getUserMapper()->isGuest() || ($service->getUserMapper()->isGuest() && $service->getConf()['comments_forall']))) {
            $comment_post_data['author'] = $service->getUserMapper()->getUser()->getUsername();
        }

        $ret = $image->toArray();
        $ret['rates'] = $rating;

        $ret['categories'] = $related_categories;

        $ret['tags'] = $related_tags;

        if (isset($comment_post_data)) {
            $ret['comment_post'] = [
                Server::WS_XML_ATTRIBUTES => $comment_post_data
            ];
        }
        $ret['comments_paging'] = [
            'page' => $params['comments_page'],
            'per_page' => $params['comments_per_page'],
            'count' => count($related_comments),
            'total_count' => $nb_comments,
        ];

        $ret['comments'] = $related_comments;

        return ['image' => $ret];
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
        if (!$service->getImageMapper()->getRepository()->isAuthorizedToUser($service->getUserMapper()->getUser()->getUserInfos()->getForbiddenCategories(), $params['image_id'])) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Invalid image_id or access denied');
        }

        $result = $service->getRateMapper()->ratePicture($params['image_id'], (int)$params['rate'], $service->getRequest()->getClientIp());

        if (is_null($result['score'])) {
            return new Error(403, 'Forbidden or rate not in ' . implode(',', $service->getConf()['rate_items']));
        }

        return $result;
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

        $search_result = $service->getSearchMapper()->getQuickSearchResults($params['query'], $service->getUserMapper()->getUser());

        $image_ids = array_slice(
            $search_result['items'],
            $params['page'] * $params['per_page'],
            $params['per_page']
        );

        if (count($image_ids)) {
            $image_ids = array_flip($image_ids);
            foreach ($service->getImageMapper()->getRepository()->findBy(['id' => $image_ids]) as $image) {
                $image_infos = $image->toArray();
                $image_infos = array_merge($image_infos, \Phyxo\Functions\Ws\Main::stdGetUrls($image, $service));
                $images[$image_ids[$image['id']]] = $image_infos;
            }
            ksort($images, SORT_NUMERIC);
            $images = array_values($images);
        }

        return [
            'paging' => [
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images),
                'total_count' => count($search_result['items']),
            ],
            'images' => $images,
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

        $service->getImageMapper()->getRepository()->updateFieldForImages($params['image_id'], 'level', (int)$params['level']);
        $service->getUserMapper()->invalidateUserCache();
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
        $imageAlbumRepository = $service->getManagerRegistry()->getRepository(ImageAlbum::class);

        // does the image really exist?
        if (is_null($service->getImageMapper()->getRepository()->find($params['image_id']))) {
            return new Error(404, 'image_id not found');
        }

        // is the image associated to that album?
        if (!$imageAlbumRepository->isImageAssociatedToAlbum($params['image_id'], $params['category_id'])) {
            return new Error(404, 'This image is not associated to that album');
        }

        // what is the current higher rank for that album?
        if ($max_rank = $imageAlbumRepository->maxRankForAlbum($params['category_id'])) {
            if ($params['rank'] > $max_rank) {
                $params['rank'] = $max_rank + 1;
            }
        } else {
            $params['rank'] = 1;
        }

        // update rank for all other photos in the same album
        $imageAlbumRepository->updateRankForAlbum($params['rank'], $params['category_id']);

        // set the new rank for the photo
        $imageAlbumRepository->updateRankForImage($params['rank'], $params['image_id'], $params['category_id']);

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

        $upload_dir = $service->getUploadDir() . '/buffer';
        try {
            $fs = new Filesystem();
            $fs->mkdir($upload_dir);
        } catch (IOException $e) {
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
        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);

        if (is_null($image)) {
            return new \Phyxo\Ws\Error(404, "image_id not found");
        }

        // we do not take the imported "thumb" into account
        if ($params['type'] === 'thumb') {
            self::remove_chunks($image->getMd5sum(), $params['type'], $service);
            return true;
        }

        // we only care about the "original"
        $original_type = 'file';
        if ($params['type'] === 'high') {
            $original_type = 'high';
        }

        $file_path = $upload_dir = $service->getUploadDir() . '/buffer/' . $image->getMd5sum() . '-original';

        self::merge_chunks($file_path, $image->getMd5sum(), $original_type, $service);
        chmod($file_path, 0644);

        // if we receive the "file", we only update the original if the "file" is bigger than current original
        if ($params['type'] === 'file') {
            $do_update = false;

            $infos = Utils::image_infos($file_path);

            if ($infos['width'] > $image->getWidth() || $infos['height'] > $image->getHeight() || $infos['filesize'] > $image->getFilesize()) {
                $do_update = true;
            }

            if (!$do_update) {
                unlink($file_path);
                return true;
            }
        }

        $image_id = self::addUploadedFile(
            $service,
            $file_path,
            $image->getFile(),
            [],
            null,
            $params['image_id'],
            $image->getMd5sum() // we force the md5sum to remain the same
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
            $image = $service->getImageMapper()->getRepository()->find($params['image_id']);
            if (is_null($image)) {
                return new Error(404, 'image_id not found');
            }
        }

        // does the image already exists ?
        if ($params['check_uniqueness']) {
            $image = null;
            if ('md5sum' === $service->getConf()['uniqueness_mode']) {
                $image = $service->getImageMapper()->getRepository()->findBy(['md5sum' => $params['original_sum']]);
            } elseif ('filename' === $service->getConf()['uniqueness_mode']) {
                $image = $service->getImageMapper()->getRepository()->findBy(['file' => $params['original_filename']]);
            }

            if (!is_null($image)) {
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

        $file_path = $service->getUploadDir() . '/buffer/' . $params['original_sum'] . '-original';

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

        $image = $service->getImageMapper()->getRepository()->find($image_id);
        if (isset($params['name'])) {
            $image->setName($params['name']);
        }

        if (isset($params['author'])) {
            $image->setAuthor($params['author']);
        }

        if (isset($params['comment'])) {
            $image->setComment($params['comment']);
        }

        if (isset($params['date_creation'])) {
            $image->setDateCreation(new \DateTime($params['date_creation']));
        }
        $service->getImageMapper()->getRepository()->addOrUpdateImage($image);

        $url_params = ['image_id' => $image_id];

        // let's add links between the image and the categories
        if (isset($params['categories'])) {
            self::addImageAlbumRelations($service, $image_id, $params['categories'], $replace_mode = false);

            if (preg_match('/^\d+/', $params['categories'], $matches)) {
                $category_id = $matches[0];

                $album = $service->getAlbumMapper()->getRepository()->find($category_id);
                $url_params['type'] = 'category';
                $url_params['element_id'] = $album->getId();
            }
        }

        // and now, let's create tag associations
        if (!empty($params['tag_ids'])) {
            $service->getTagMapper()->setTags(explode(',', $params['tag_ids']), $image_id, $service->getUserMapper()->getUser());
        }

        $service->getUserMapper()->invalidateUserCache();

        return [
            'image_id' => $image_id,
            'url' => $service->getRouter()->generate('picture', $url_params),
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
            if (is_null($service->getImageMapper()->getRepository()->find($params['image_id']))) {
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

        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);
        if (isset($params['name'])) {
            $image->setName($params['name']);
        }

        if (isset($params['author'])) {
            $image->setAuthor($params['author']);
        }

        if (isset($params['comment'])) {
            $image->setComment($params['comment']);
        }

        if (isset($params['level'])) {
            $image->setLevel($params['level']);
        }

        if (isset($params['date_creation'])) {
            $image->setDateCreation(new \DateTime($params['date_creation']));
        }

        $service->getImageMapper()->getRepository()->addOrUpdateImage($image);

        if (!empty($params['tags'])) {
            $tag_ids = [];
            if (is_array($params['tags'])) {
                foreach ($params['tags'] as $tag_name) {
                    $tag_ids[] = $service->getTagMapper()->tagIdFromTagName($tag_name);
                }
            } else {
                $tag_names = preg_split('~(?<!\\\),~', $params['tags']);
                foreach ($tag_names as $tag_name) {
                    $tag_ids[] = $service->getTagMapper()->tagIdFromTagName(preg_replace('#\\\\*,#', ',', $tag_name));
                }
            }

            $service->getTagMapper()->addTags($tag_ids, [$image_id], $service->getUserMapper()->getUser());
        }

        $url_params = ['image_id' => $image_id];

        if (!empty($params['category'])) {
            $album = $service->getAlbumMapper()->getRepository()->fin($params['category'][0]);
            $url_params['type'] = 'category';
            $url_params['element_id'] = $album->getId();
        }

        return [
            'image_id' => $image_id,
            'url' => $service->getRouter()->generate('picture', $url_params),
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
        // @TODO add token

        $upload_dir = $service->getUploadDir() . '/buffer';
        try {
            $fs = new Filesystem();
            $fs->mkdir($upload_dir);
        } catch (IOException $e) {
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
            $service->getTagMapper()->sync_metadata([$image_id], $service->getUserMapper()->getUser());

            $image = $service->getImageMapper()->getRepository()->find($image_id);

            $nb_photos_in = $service->getManagerRegistry()->getRepository(ImageAlbum::class)->countImagesByAlbum();
            $category_name = $service->getAlbumMapper()->getRepository()->find($params['category'][0])->getName();
            $derivative_image = new DerivativeImage(
                $image,
                $service->getImageStandardParams()->getByType(ImageStandardParams::IMG_THUMB),
                $service->getImageStandardParams()
            );

            return [
                'image_id' => $image_id,
                'src' => $service->getRouter()->generate('media', $derivative_image->relativeThumbInfos()),
                'name' => $image->getName(),
                'category' => [
                    'id' => $params['category'][0],
                    'nb_photos' => $nb_photos_in[$params['category'][0]],
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

        if ($service->getConf()['uniqueness_mode'] === 'md5sum') {
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

            $id_of_md5 = [];
            foreach ($service->getImageMapper()->getRepository()->findBy(['md5sum' => $md5sums]) as $image) {
                $id_of_md5[$image->getMd5sum()] = $image->getId();
            }

            foreach ($md5sums as $md5sum) {
                $res[$md5sum] = null;
                if (isset($id_of_md5[$md5sum])) {
                    $res[$md5sum] = $id_of_md5[$md5sum];
                }
            }
        } elseif ($service->getConf()['uniqueness_mode'] === 'filename') {
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

            $id_of_filename = [];
            foreach ($service->getImageMapper()->getRepository()->findBy(['file' => $filenames]) as $image) {
                $id_of_md5[$image->getFile()] = $image->getId();
            }

            foreach ($filenames as $filename) {
                $res[$filename] = null;
                /** @phpstan-ignore-next-line */
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
        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);

        if (is_null($image)) {
            return new Error(404, 'image_id not found');
        }

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
            if (md5_file($image->getPath()) != $params[$compare_type . '_sum']) {
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

        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);
        if (!$service->getSecurity()->isGranted(TagVoter::ADD, $image) || !$service->getSecurity()->isGranted(TagVoter::DELETE, $image)) {
            return new Error(403, 'You are not allowed to add nor delete tags');
        }

        if (empty($params['tags'])) {
            $params['tags'] = [];
        }

        $current_tags_ids = $removed_tags_ids = $new_tags_ids = [];
        foreach ($service->getTagMapper()->getRepository()->getTagsByImage($params['image_id']) as $tag) {
            $current_tags_ids[] = $tag->getId();
        }
        $current_tags = array_map(function ($id) {
            return '~~' . $id . '~~';
        }, $current_tags_ids);
        $removed_tags = array_diff($current_tags, $params['tags']);
        $new_tags = array_diff($params['tags'], $current_tags);

        if (count($removed_tags) > 0) {
            if ($service->getSecurity()->isGranted(TagVoter::DELETE, $image) == false) {
                return new Error(403, 'You are not allowed to delete tags');
            }
        }
        if (count($new_tags) > 0) {
            if ($service->getSecurity()->isGranted(TagVoter::ADD, $image) == false) {
                return new Error(403, 'You are not allowed to add tags');
            }
        }

        try {
            if (empty($params['tags'])) { // remove all tags for an image
                if (isset($service->getConf()['delete_tags_immediately']) && $service->getConf()['delete_tags_immediately'] == 0) {
                    $service->getTagMapper()->toBeValidatedTags($image, $current_tags_ids, $service->getUserMapper()->getUser(), ImageTag::STATUS_TO_DELETE);
                } else {
                    $service->getManagerRegistry()->getRepository(ImageTag::class)->deleteForImage($params['image_id']);
                }
            } else {
                // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is 1
                if (count($removed_tags) > 0) {
                    $removed_tags_ids = $service->getTagMapper()->getTagsIds($removed_tags);
                    if (isset($service->getConf()['delete_tags_immediately']) && $service->getConf()['delete_tags_immediately'] == 0) {
                        $service->getTagMapper()->toBeValidatedTags($image, $removed_tags_ids, $service->getUserMapper()->getUser(), ImageTag::STATUS_TO_DELETE);
                    } else {
                        $service->getTagMapper()->dissociateTags($removed_tags_ids, $params['image_id']);
                    }
                }

                if (count($new_tags) > 0) {
                    $new_tags_ids = $service->getTagMapper()->getTagsIds($new_tags);
                    if (isset($service->getConf()['publish_tags_immediately']) && $service->getConf()['publish_tags_immediately'] == 0) {
                        $service->getTagMapper()->toBeValidatedTags($image, $new_tags_ids, $service->getUserMapper()->getUser(), ImageTag::STATUS_TO_ADD);
                    } else {
                        $service->getTagMapper()->associateTags($new_tags_ids, $params['image_id'], $service->getUserMapper()->getUser());
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
        $image = $service->getImageMapper()->getRepository()->find($params['image_id']);

        if (is_null($image)) {
            return new Error(404, 'image_id not found');
        }

        if (isset($params['name'])) {
            $image->setName($params['name']);
        }

        if (isset($params['author'])) {
            $image->setAuthor($params['author']);
        }

        if (isset($params['comment'])) {
            $image->setComment($params['comment']);
        }

        if (isset($params['level'])) {
            $image->setLevel($params['level']);
        }

        if (isset($params['date_creation'])) {
            $image->setDateCreation(new \DateTime($params['date_creation']));
        }

        if (isset($params['file'])) {
            if (!empty($image->getStorageCategoryId())) {
                return new Error(
                    500,
                    '[ws_images_setInfo] updating "file" is forbidden on photos added by synchronization'
                );
            }

            $image->setFile($params['file']);
        }

        $service->getImageMapper()->getRepository()->addOrUpdateImage($image);

        if (isset($params['categories'])) {
            self::addImageAlbumRelations(
                $service,
                $params['image_id'],
                $params['categories'],
                ('replace' == $params['multiple_value_mode'] ? true : false)
            );
        }

        // and now, let's create tag associations
        if (isset($params['tag_ids'])) {
            $tag_ids = [];

            foreach (explode(',', $params['tag_ids']) as $candidate) {
                $candidate = trim($candidate);

                if (preg_match('/^\d+$/', $candidate)) {
                    $tag_ids[] = $candidate;
                }
            }

            if ('replace' == $params['multiple_value_mode']) {
                $service->getTagMapper()->setTags($tag_ids, $params['image_id'], $service->getUserMapper()->getUser());
            } elseif ('append' == $params['multiple_value_mode']) {
                $service->getTagMapper()->addTags($tag_ids, [$params['image_id']], $service->getUserMapper()->getUser());
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

        $number_of_elements_deleted = $service->getImageMapper()->deleteElements($image_ids, true);
        $service->getUserMapper()->invalidateUserCache();

        return $number_of_elements_deleted;
    }

    /**
     * API method
     * Checks if Phyxo is ready for upload
     * @param mixed[] $params
     */
    public static function checkUpload($params, Server $service)
    {
        $message = '';
        if (!is_dir($service->getUploadDir())) {
            if (!is_writable(dirname($service->getUploadDir()))) {
                $message = sprintf('Create the "%s" directory at the root of your Phyxo installation', basename($service->getUploadDir()));
            }
        } else {
            if (!is_writable($service->getUploadDir())) {
                $message = sprintf('Give write access (chmod 777) to "%s" directory at the root of your Phyxo installation', basename($service->getUploadDir()));
            }
        }

        return ['message' => $message, 'ready_for_upload' => $message === ''];
    }

    // protected methods, not part of the API

    /**
     * Sets associations of an image
     * @param int $image_id
     * @param string $categories_string - "cat_id[,rank];cat_id[,rank]"
     * @param bool $replace_mode - removes old associations
     */
    protected static function addImageAlbumRelations(Server $service, int $image_id, $categories_string, $replace_mode = false)
    {
        /* let's add links between the image and the albums
         *
         * $params['categories'] should look like 123,12;456,auto;789 which means:
         *
         * 1. associate with album 123 on rank 12
         * 2. associate with album 456 on automatic rank
         * 3. associate with album 789 on automatic rank
         */
        $album_ids = [];
        $rank_on_album = [];
        $search_current_ranks = false;

        $tokens = explode(';', $categories_string);
        foreach ($tokens as $token) {
            list($album_id, $rank) = explode(',', $token);

            if (!preg_match('/^\d+$/', $album_id)) {
                continue;
            }

            $album_ids[] = $album_id;

            $rank = 'auto';
            $rank_on_album[$album_id] = $rank;

            if ($rank === 'auto') {
                $search_current_ranks = true;
            }
        }

        $album_ids = array_unique($album_ids);

        if (count($album_ids) === 0) {
            return new Error(
                500,
                '[\Phyxo\Functions\Ws\Images::addImageAlbumRelations] there is no category defined in "' . $categories_string . '"'
            );
        }


        $image = $service->getImageMapper()->getRepository()->find($image_id);

        // in case of replace mode, we first check the existing associations
        if ($replace_mode) {
            $image->getImageAlbums()->clear();
        }

        $album_ids = [];
        foreach ($service->getAlbumMapper()->getRepository()->findBy(['id' => $album_ids]) as $album) {
            $album_ids[] = $album->getId();
            if (!isset($current_rank_of[$album->getId()])) {
                $current_rank_of[$album->getId()] = 0;
            }

            if ($rank_on_album[$album->getId()] === 'auto') {
                $rank_on_album[$album->getId()] = $current_rank_of[$album->getId()] + 1;
            }

            $image_album = new ImageAlbum();
            $image_album->setImage($image);
            $image_album->setAlbum($album);
            $image_album->setRank($rank_on_album[$album->getId()]);
            $album->addImageAlbum($image_album);
        }

        $service->getAlbumMapper()->updateAlbums($album_ids);
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

        $upload_dir = $service->getUploadDir() . '/buffer';
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
        $upload_dir = $service->getUploadDir() . '/buffer';
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

        $fs = new Filesystem();
        $file_path = null;
        $now = new \DateTime();
        $upload_dir = $service->getUploadDir();

        if (isset($image_id)) { // this photo already exists, we update it
            $image = $service->getImageMapper()->getRepository()->find($image_id);
            if (is_null($image)) {
                throw new \Exception('this photo does not exist in the database');
            }
            $file_path = $image->getPath();

            // delete all physical files related to the photo (thumbnail, web site, HD)
            $service->getImageMapper()->deleteElementFiles([$image_id]);
        } else {
            $year = $now->format('Y');
            $month = $now->format('m');
            $day = $now->format('d');

            // upload directory hierarchy
            $filename_dir = sprintf('%s/%s/%s/%s', $upload_dir, $year, $month, $day);

            // compute file path
            $date_string = $now->format('YmdHis');
            $random_string = substr($md5sum, 0, 8);
            $filename_wo_ext = $date_string . '-' . $random_string;
            $file_path = $filename_dir . '/' . $filename_wo_ext . '.';

            list($width, $height, $type) = getimagesize($source_filepath);

            if ($type === IMAGETYPE_PNG) {
                $file_path .= 'png';
            } elseif ($type === IMAGETYPE_GIF) {
                $file_path .= 'gif';
            } elseif ($type === IMAGETYPE_JPEG) {
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

            $fs->mkdir($filename_dir);
        }

        if (is_uploaded_file($source_filepath)) {
            move_uploaded_file($source_filepath, $file_path);
        } else {
            rename($source_filepath, $file_path);
        }
        $fs->chmod($file_path, 0644);

        $imageOptimizer = new ImageOptimizer($file_path, $service->getImageLibrary());
        $imageOptimizer->mainResize(
            $file_path,
            $service->getConf()['original_resize_maxwidth'],
            $service->getConf()['original_resize_maxheight'],
            $service->getConf()['original_resize_quality'],
            $service->getConf()['upload_form_automatic_rotation'],
            false
        );

        // we need to save the rotation angle in the database to compute
        // width/height of "multisizes"
        $rotation_angle = ImageOptimizer::getRotationAngle($file_path);
        $rotation = ImageOptimizer::getRotationCodeFromAngle($rotation_angle);

        list($width, $height) = getimagesize($file_path);
        $filesize = (int) floor(filesize($file_path) / 1024);

        if (isset($image_id)) {
            $image = $service->getImageMapper()->getRepository()->find($image_id);
            $image->setFile(!empty($original_filename) ? $original_filename : basename($file_path));
            $image->setFilesize($filesize);
            $image->setWidth($width);
            $image->setHeight($height);
            $image->setMd5sum($md5sum);
            $image->setAddedBy($service->getUserMapper()->getUser()->getId());
            $image->setRotation($rotation);

            if (isset($level)) {
                $image->setLevel($level);
            }
            $service->getImageMapper()->getRepository()->addOrUpdateImage($image);
        } else {
            $image = new EntityImage();
            $image->setFile(!empty($original_filename) ? $original_filename : basename($file_path));
            $image->setName(Utils::get_name_from_file($image->getFile()));
            $image->setDateAvailable($now);
            $image->setPath(preg_replace('#^' . preg_quote(dirname($upload_dir)) . '#', '.', realpath($file_path)));
            $image->setFilesize($filesize);
            $image->setWidth($width);
            $image->setHeight($height);
            $image->setMd5sum($md5sum);
            $image->setAddedBy($service->getUserMapper()->getUser()->getId());
            $image->setRotation($rotation);

            if (isset($level)) {
                $image->setLevel($level);
            }

            // if (isset($representative_ext)) {
            //     $image->setRepresentativeExt($representative_ext);
            // }

            $image_id = $service->getImageMapper()->getRepository()->addOrUpdateImage($image);
        }

        if (count($categories) > 0) {
            $service->getAlbumMapper()->associateImagesToAlbums([$image_id], $categories);
        }

        // update metadata from the uploaded file (exif/iptc)
        if ($service->getConf()['use_exif'] && !function_exists('exif_read_data')) {
            $service->getConf()['use_exif'] = false;
        }

        $service->getUserMapper()->invalidateUserCache();

        return $image_id;
    }
}
