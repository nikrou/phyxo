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

namespace App\Controller;

use App\DataMapper\AlbumMapper;
use Symfony\Component\HttpFoundation\Response;
use App\DataMapper\TagMapper;
use App\DataMapper\CommentMapper;
use App\DataMapper\UserMapper;
use App\DataMapper\ImageMapper;
use Phyxo\Ws\Server;
use Phyxo\Ws\Protocols\RestRequestHandler;
use Phyxo\Ws\Protocols\JsonEncoder;
use Phyxo\Conf;
use Phyxo\DBLayer\iDBLayer;
use Symfony\Component\Routing\RouterInterface;
use App\DataMapper\RateMapper;
use Phyxo\EntityManager;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\SearchMapper;
use App\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Utils\UserManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;

class WsController extends AbstractController
{
    private $service;

    public function index(UserMapper $userMapper, TagMapper $tagMapper, CommentMapper $commentMapper, Conf $conf, iDBLayer $conn, EntityManager $em, UserProvider $userProvider,
                           UserManager $userManager, UserPasswordEncoderInterface $passwordEncoder, RateMapper $rateMapper, SearchMapper $searchMapper, RouterInterface $router,
                           string $phyxoVersion, ImageStandardParams $image_std_params, string $pemURL, Security $security, ParameterBagInterface $params,
                           ImageMapper $imageMapper, Request $request, ManagerRegistry $managerRegistry, AlbumMapper $albumMapper)
    {
        $this->service = new Server($params->get('upload_dir'));
        $this->service->setRequest($request);
        $this->service->addUserMapper($userMapper);
        $this->service->addTagMapper($tagMapper);
        $this->service->addCommentMapper($commentMapper);
        $this->service->addAlbumMapper($albumMapper);
        $this->service->addRateMapper($rateMapper);
        $this->service->addSearchMapper($searchMapper);
        $this->service->addImageMapper($imageMapper);
        $this->service->setHandler(new RestRequestHandler());
        $this->service->setEncoder(new JsonEncoder());
        $this->service->setCoreVersion($phyxoVersion);
        $this->service->setConf($conf);
        $this->service->setConnection($conn);
        $this->service->setEntityManager($em);
        $this->service->setRouter($router);
        $this->service->setEntityManager($em);
        $this->service->setUserManager($userManager);
        $this->service->setImageStandardParams($image_std_params);
        $this->service->setPasswordEncoder($passwordEncoder);
        $this->service->setExtensionsURL($pemURL);
        $this->service->setSecurity($security);
        $this->service->setManagerRegistry($managerRegistry);
        $this->service->setUserProvider($userProvider);
        $this->service->setParams($params);

        $this->addDefaultMethods();

        return new Response($this->service->run());
    }

    protected function addDefaultMethods()
    {
        $f_params = [
            'f_min_rate' => ['default' => null, 'type' => Server::WS_TYPE_FLOAT],
            'f_max_rate' => ['default' => null, 'type' => Server::WS_TYPE_FLOAT],
            'f_min_hit' => ['default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            'f_max_hit' => ['default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            'f_min_ratio' => ['default' => null, 'type' => Server::WS_TYPE_FLOAT | Server::WS_TYPE_POSITIVE],
            'f_max_ratio' => ['default' => null, 'type' => Server::WS_TYPE_FLOAT | Server::WS_TYPE_POSITIVE],
            'f_max_level' => ['default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            'f_min_date_available' => ['default' => null],
            'f_max_date_available' => ['default' => null],
            'f_min_date_created' => ['default' => null],
            'f_max_date_created' => ['default' => null],
        ];

        $this->service->addMethod(
            'pwg.getVersion',
            '\Phyxo\Functions\Ws\Main::getVersion',
            null,
            'Returns the Phyxo version.'
        );

        $this->service->addMethod(
            'pwg.getInfos',
            '\Phyxo\Functions\Ws\Main::getInfos',
            null,
            'Returns general informations.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.caddie.add',
            '\Phyxo\Functions\Ws\Caddie::add',
            ['image_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID]],
            'Adds elements to the caddie. Returns the number of elements added.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.getImages',
            '\Phyxo\Functions\Ws\Category::getImages',
            array_merge([
                'cat_id' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'recursive' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'per_page' => ['default' => 100, 'maxValue' => $this->service->getConf()['ws_max_images_per_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'order' => ['default' => null, 'info' => 'id, file, name, hit, rating_score, date_creation, date_available, random'],
            ], $f_params),
            'Returns elements for the corresponding categories.<br><b>cat_id</b> can be empty if <b>recursive</b> is true.<br><b>order</b> comma separated fields for sorting'
        );

        $this->service->addMethod(
            'pwg.categories.getList',
            '\Phyxo\Functions\Ws\Category::getList',
            [
                'cat_id' => [
                    'default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE,
                    'info' => 'Parent category. "0" or empty for root.'
                ],
                'recursive' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'public' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'tree_output' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'fullname' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
            ],
            'Returns a list of categories.'
        );

        $this->service->addMethod(
            'pwg.getMissingDerivatives',
            '\Phyxo\Functions\Ws\Main::getMissingDerivatives',
            array_merge([
                'types' => [
                    'default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY,
                    'info' => 'square, thumb, 2small, xsmall, small, medium, large, xlarge, xxlarge'
                ],
                'ids' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'max_urls' => ['default' => 200, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'prev_page' => ['default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            ], $f_params),
            'Returns a list of derivatives to build.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.addComment',
            '\Phyxo\Functions\Ws\Image::addComment',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'author' => ['default' => $this->service->getUserMapper()->getUser()->getUsername()],
                'content' => [],
                'key' => [],
            ],
            'Adds a comment to an image.',
            ['post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.getInfo',
            '\Phyxo\Functions\Ws\Image::getInfo',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'comments_page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'comments_per_page' => ['default' => $this->service->getConf()['nb_comment_page'], 'maxValue' => 2 * $this->service->getConf()['nb_comment_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            ],
            'Returns information about an image.'
        );

        $this->service->addMethod(
            'pwg.images.rate',
            '\Phyxo\Functions\Ws\Image::rate',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'rate' => ['type' => Server::WS_TYPE_FLOAT],
            ],
            'Rates an image.'
        );

        $this->service->addMethod(
            'pwg.images.search',
            '\Phyxo\Functions\Ws\Image::search',
            array_merge([
                'query' => [],
                'per_page' => ['default' => 100, 'maxValue' => $this->service->getConf()['ws_max_images_per_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'order' => ['default' => null, 'info' => 'id, file, name, hit, rating_score, date_creation, date_available, random'],
            ], $f_params),
            'Returns elements for the corresponding query search.'
        );

        $this->service->addMethod(
            'pwg.images.setPrivacyLevel',
            '\Phyxo\Functions\Ws\Image::setPrivacyLevel',
            [
                'image_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'level' => ['maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
            ],
            'Sets the privacy levels for the images.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.setRank',
            '\Phyxo\Functions\Ws\Image::setRank',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'category_id' => ['type' => Server::WS_TYPE_ID],
                'rank' => ['type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE | Server::WS_TYPE_NOTNULL]
            ],
            'Sets the rank of a photo for a given album.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.rates.delete',
            '\Phyxo\Functions\Ws\Rate::delete',
            [
                'user_id' => ['type' => Server::WS_TYPE_ID],
                'anonymous_id' => ['default' => null],
                'image_id' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
            ],
            'Deletes all rates for a user.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.session.getStatus',
            '\Phyxo\Functions\Ws\Session::getStatus',
            null,
            'Gets information about the current session. Also provides a token useable with admin methods.'
        );

        $this->service->addMethod(
            'pwg.session.login',
            '\Phyxo\Functions\Ws\Session::login',
            ['username', 'password'],
            'Tries to login the user.',
            ['post_only' => true]
        );

        $this->service->addMethod(
            'pwg.session.logout',
            '\Phyxo\Functions\Ws\Session::logout',
            null,
            'Ends the current session.'
        );

        $this->service->addMethod(
            'pwg.tags.getFilteredList',
            '\Phyxo\Functions\Ws\Tag::getFilteredList',
            ['q' => ['default' => '']],
            'Retrieves a filtered list of all available tags.'
        );

        $this->service->addMethod(
            'pwg.tags.getList',
            '\Phyxo\Functions\Ws\Tag::getList',
            [
                'sort_by_counter' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
            ],
            'Retrieves a list of available tags.'
        );

        $this->service->addMethod(
            'pwg.tags.getImages',
            '\Phyxo\Functions\Ws\Tag::getImages',
            array_merge([
                'tag_id' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'tag_url_name' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY],
                'tag_name' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY],
                'tag_mode_and' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'per_page' => ['default' => 100, 'maxValue' => $this->service->getConf()['ws_max_images_per_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'order' => ['default' => null, 'info' => 'id, file, name, hit, rating_score, date_creation, date_available, random'],
            ], $f_params),
            'Returns elements for the corresponding tags. Fill at least tag_id, tag_url_name or tag_name.'
        );

        $this->service->addMethod(
            'pwg.images.addChunk',
            '\Phyxo\Functions\Ws\Image::addChunk',
            [
                'data' => [],
                'original_sum' => [],
                'type' => ['default' => 'file', 'info' => 'Must be "file", for backward compatiblity "high" and "thumb" are allowed.'],
                'position' => []
            ],
            'Add a chunk of a file.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.addFile',
            '\Phyxo\Functions\Ws\Image::addFile',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'type' => ['default' => 'file', 'info ' => 'Must be "file", for backward compatiblity "high" and "thumb" are allowed.'],
                'sum' => [],
            ],
            'Add or update a file for an existing photo.<br>pwg.images.addChunk must have been called before (maybe several times).',
            ['admin_only' => true]
        );


        $this->service->addMethod(
            'pwg.images.add',
            '\Phyxo\Functions\Ws\Image::add',
            [
                'thumbnail_sum' => ['default' => null],
                'high_sum' => ['default' => null],
                'original_sum' => [],
                'original_filename' => ['default' => null, 'Provide it if "check_uniqueness" is true and $conf["uniqueness_mode"] is "filename".'],
                'name' => ['default' => null],
                'author' => ['default' => null],
                'date_creation' => ['default' => null],
                'comment' => ['default' => null],
                'categories' => ['default' => null, 'info' => 'String list "category_id[,rank];category_id[,rank]".<br>The rank is optional and is equivalent to "auto" if not given.'],
                'tag_ids' => ['default' => null, 'info' => 'Comma separated ids'],
                'level' => ['default' => 0, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'check_uniqueness' => ['default' => true, 'type' => Server::WS_TYPE_BOOL],
                'image_id' => ['default' => null, 'type' => Server::WS_TYPE_ID],
            ],
            'Add an image.<br>pwg.images.addChunk must have been called before (maybe several times).<br>Don\'t use "thumbnail_sum" and "high_sum", these parameters are here for backward compatibility.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.addSimple',
            '\Phyxo\Functions\Ws\Image::addSimple',
            [
                'category' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'name' => ['default' => null],
                'author' => ['default' => null],
                'comment' => ['default' => null],
                'level' => ['default' => 0, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'tags' => ['default' => null, 'flags' => Server::WS_PARAM_ACCEPT_ARRAY],
                'image_id' => ['default' => null, 'type' => Server::WS_TYPE_ID],
            ],
            'Add an image.<br>Use the <b>$_FILES[image]</b> field for uploading file.<br>Set the form encoding to "form-data".<br>You can update an existing photo if you define an existing image_id.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.upload',
            '\Phyxo\Functions\Ws\Image::upload',
            [
                'name' => ['default' => null],
                'category' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'level' => ['default' => 0, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'pwg_token' => [],
            ],
            'Add an image.<br>Use the <b>$_FILES[image]</b> field for uploading file.<br>Set the form encoding to "form-data".',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.delete',
            '\Phyxo\Functions\Ws\Image::delete',
            [
                'image_id' => ['flags' => Server::WS_PARAM_ACCEPT_ARRAY],
                'pwg_token' => [],
            ],
            'Deletes image(s).',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.getAdminList',
            '\Phyxo\Functions\Ws\Category::getAdminList',
            null,
            'Get albums list as displayed on admin page.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.add',
            '\Phyxo\Functions\Ws\Category::add',
            [
                'name' => [],
                'parent' => ['default' => null, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'comment' => ['default' => null],
                'visible' => ['default' => true, 'type' => Server::WS_TYPE_BOOL],
                'status' => ['default' => null, 'info' => 'public, private'],
                'commentable' => ['default' => true, 'type' => Server::WS_TYPE_BOOL],
            ],
            'Adds an album.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.delete',
            '\Phyxo\Functions\Ws\Category::delete',
            [
                'category_id' => ['flags' => Server::WS_PARAM_ACCEPT_ARRAY],
                'photo_deletion_mode' => ['default' => 'delete_orphans'],
                'pwg_token' => [],
            ],
            'Deletes album(s).<br><b>photo_deletion_mode</b> can be "no_delete" (may create orphan photos), "delete_orphans"
    (default mode, only deletes photos linked to no other album) or "force_delete" (delete all photos, even those linked to other albums)',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.move',
            '\Phyxo\Functions\Ws\Category::move',
            [
                'category_id' => ['flags' => Server::WS_PARAM_ACCEPT_ARRAY],
                'parent' => ['type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'pwg_token' => [],
            ],
            'Move album(s).
    <br>Set parent as 0 to move to gallery root. Only virtual categories can be moved.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.setRepresentative',
            '\Phyxo\Functions\Ws\Category::setRepresentative',
            [
                'category_id' => ['type' => Server::WS_TYPE_ID],
                'image_id' => ['type' => Server::WS_TYPE_ID],
            ],
            'Sets the representative photo for an album. The photo doesn\'t have to belong to the album.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.tags.getAdminList',
            '\Phyxo\Functions\Ws\Tag::getAdminList',
            null,
            '<b>Admin only.</b>',
            ['admin_only' => true]
        );

        $this->service->addMethod( // @TODO: create multiple tags
            'pwg.tags.add',
            '\Phyxo\Functions\Ws\Tag::add',
            ['name'],
            'Adds a new tag.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.exist',
            '\Phyxo\Functions\Ws\Image::exist',
            [
                'md5sum_list' => ['default' => null],
                'filename_list' => ['default' => null],
            ],
            'Checks existence of images.
    <br>Give <b>md5sum_list</b> if $conf[uniqueness_mode]==md5sum. Give <b>filename_list</b> if $conf[uniqueness_mode]==filename.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.checkFiles',
            '\Phyxo\Functions\Ws\Image::checkFiles',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'file_sum' => ['default' => null],
                'thumbnail_sum' => ['default' => null],
                'high_sum' => ['default' => null],
            ],
            'Checks if you have updated version of your files for a given photo, the answer can be "missing", "equals" or "differs".
    <br>Don\'t use "thumbnail_sum" and "high_sum", these parameters are here for backward compatibility.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.checkUpload',
            '\Phyxo\Functions\Ws\Image::checkUpload',
            null,
            'Checks if Phyxo is ready for upload.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.setRelatedTags',
            '\Phyxo\Functions\Ws\Image::setRelatedTags',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'tags' => ['default' => null, 'flags' => Server::WS_PARAM_FORCE_ARRAY],
            ],
            'Add/update/delete tags associated to an existing image. Actions availables depend on permissions.',
            ['post_only' => true]
        );

        $this->service->addMethod(
            'pwg.images.setInfo',
            '\Phyxo\Functions\Ws\Image::setInfo',
            [
                'image_id' => ['type' => Server::WS_TYPE_ID],
                'file' => ['default' => null],
                'name' => ['default' => null],
                'author' => ['default' => null],
                'date_creation' => ['default' => null],
                'comment' => ['default' => null],
                'categories' => [
                    'default' => null,
                    'info' => 'String list "category_id[,rank];category_id[,rank]".<br>The rank is optional and is equivalent to "auto" if not given.'
                ],
                'tag_ids' => [
                    'default' => null,
                    'info' => 'Comma separated ids'
                ],
                'level' => ['default' => null, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'single_value_mode' => ['default' => 'fill_if_empty'],
                'multiple_value_mode' => ['default' => 'append'],
            ],
            'Changes properties of an image.
    <br><b>single_value_mode</b> can be "fill_if_empty" (only use the input value if the corresponding values is currently empty) or "replace"
    (overwrite any existing value) and applies to single values properties like name/author/date_creation/comment.
    <br><b>multiple_value_mode</b> can be "append" (no change on existing values, add the new values) or "replace" and applies to multiple values properties like tag_ids/categories.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.categories.setInfo',
            '\Phyxo\Functions\Ws\Category::setInfo',
            [
                'category_id' => ['type' => Server::WS_TYPE_ID],
                'name' => ['default' => null],
                'comment' => ['default' => null],
            ],
            'Changes properties of an album.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.plugins.getList',
            '\Phyxo\Functions\Ws\Plugin::getList',
            null,
            'Gets the list of plugins with id, name, version, state and description.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.plugins.performAction',
            '\Phyxo\Functions\Ws\Plugin::performAction',
            [
                'action' => ['info' => 'install, activate, deactivate, uninstall, delete'],
                'plugin' => [],
                'pwg_token' => [],
            ],
            null,
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.themes.performAction',
            '\Phyxo\Functions\Ws\Theme::performAction',
            [
                'action' => ['info' => 'activate, deactivate, delete, set_default'],
                'theme' => [],
                'pwg_token' => [],
            ],
            null,
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.extensions.update',
            '\Phyxo\Functions\Ws\Extension::update',
            [
                'type' => ['info' => 'plugins, languages, themes'],
                'id' => [],
                'revision' => [],
                'pwg_token' => [],
            ],
            '<b>Webmaster only.</b>',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.extensions.ignoreUpdate',
            '\Phyxo\Functions\Ws\Extension::ignoreupdate',
            [
                'type' => ['default' => null, 'info' => 'plugins, languages, themes'],
                'id' => ['default' => null],
                'reset' => ['default' => false, 'type' => Server::WS_TYPE_BOOL, 'info' => 'If true, all ignored extensions will be reinitilized.'],
                'pwg_token' => [],
            ],
            '<b>Webmaster only.</b> Ignores an extension if it needs update.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.extensions.checkUpdates',
            '\Phyxo\Functions\Ws\Extension::checkupdates',
            null,
            'Checks if phyxo or extensions are up to date.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.getList',
            '\Phyxo\Functions\Ws\Group::getList',
            [
                'group_id' => ['flags' => Server::WS_PARAM_OPTIONAL | Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'name' => ['flags' => Server::WS_PARAM_OPTIONAL, 'info' => 'Use "%" as wildcard.'],
                'per_page' => ['default' => 100, 'maxValue' => $this->service->getConf()['ws_max_users_per_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'order' => ['default' => 'name', 'info' => 'id, name, nb_users, is_default'],
            ],
            'Retrieves a list of all groups. The list can be filtered.',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.add',
            '\Phyxo\Functions\Ws\Group::add',
            [
                'name' => [],
                'is_default' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
            ],
            'Creates a group and returns the new group record.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.delete',
            '\Phyxo\Functions\Ws\Group::delete',
            [
                'group_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'pwg_token' => [],
            ],
            'Deletes a or more groups. Users and photos are not deleted.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.setInfo',
            '\Phyxo\Functions\Ws\Group::setInfo',
            [
                'group_id' => ['type' => Server::WS_TYPE_ID],
                'name' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'is_default' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_BOOL],
                'pwg_token' => [],
            ],
            'Updates a group. Leave a field blank to keep the current value.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.addUser',
            '\Phyxo\Functions\Ws\Group::addUser',
            [
                'group_id' => ['type' => Server::WS_TYPE_ID],
                'user_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'pwg_token' => [],
            ],
            'Adds one or more users to a group.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.groups.deleteUser',
            '\Phyxo\Functions\Ws\Group::deleteUser',
            [
                'group_id' => ['type' => Server::WS_TYPE_ID],
                'user_id' => [
                    'flags' => Server::WS_PARAM_FORCE_ARRAY,
                    'type' => Server::WS_TYPE_ID
                ],
                'pwg_token' => [],
            ],
            'Removes one or more users from a group.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.users.getList',
            '\Phyxo\Functions\Ws\User::getList',
            [
                'user_id' => [
                    'flags' => Server::WS_PARAM_OPTIONAL | Server::WS_PARAM_FORCE_ARRAY,
                    'type' => Server::WS_TYPE_ID
                ],
                'username' => ['flags' => Server::WS_PARAM_OPTIONAL, 'info' => 'Use "%" as wildcard.'],
                'status' => ['flags' => Server::WS_PARAM_OPTIONAL | Server::WS_PARAM_FORCE_ARRAY, 'info' => 'guest,normal,admin,webmaster'],
                'min_level' => ['default' => 0, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'group_id' => ['flags' => Server::WS_PARAM_OPTIONAL | Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'per_page' => ['default' => 100, 'maxValue' => $this->service->getConf()['ws_max_users_per_page'], 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'page' => ['default' => 0, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'order' => ['default' => 'id', 'info' => 'id, username, level, email'],
                'display' => ['default' => 'basics', 'info' => 'Comma saparated list (see method description)'],
            ],
            'Retrieves a list of all the users.<br><br><b>display</b> controls which data are returned, possible values are:<br>all, basics, none,<br>username, email, status, level, groups,<br>language, theme, nb_image_page, recent_period, expand, show_nb_comments, show_nb_hits,<br>enabled_high, registration_date, registration_date_string, registration_date_since, last_visit, last_visit_string, last_visit_since<br><b>basics</b> stands for "username,email,status,level,groups"',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.users.add',
            '\Phyxo\Functions\Ws\User::add',
            [
                'username' => [],
                'password' => ['default' => null],
                'password_confirm' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'email' => ['default' => null],
                'send_password_by_mail' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'pwg_token' => [],
            ],
            'Registers a new user.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.users.delete',
            '\Phyxo\Functions\Ws\User::delete',
            [
                'user_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'pwg_token' => [],
            ],
            'Deletes on or more users. Photos owned by this user are not deleted.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.users.setInfo',
            '\Phyxo\Functions\Ws\User::setInfo',
            [
                'user_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'username' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'password' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'email' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'status' => ['flags' => Server::WS_PARAM_OPTIONAL, 'info' => 'guest,normal,admin,webmaster'],
                'level' => ['flags' => Server::WS_PARAM_OPTIONAL, 'maxValue' => max($this->service->getConf()['available_permission_levels']), 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'language' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'theme' => ['flags' => Server::WS_PARAM_OPTIONAL],
                'group_id' => ['flags' => Server::WS_PARAM_OPTIONAL | Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_INT],
                // bellow are parameters removed in a future version
                'nb_image_page' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE | Server::WS_TYPE_NOTNULL],
                'recent_period' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_INT | Server::WS_TYPE_POSITIVE],
                'expand' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_BOOL],
                'show_nb_comments' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_BOOL],
                'show_nb_hits' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_BOOL],
                'enabled_high' => ['flags' => Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_BOOL],
                'pwg_token' => [],
            ],
            'Updates a user. Leave a field blank to keep the current value.<br>"username", "password" and "email" are ignored if "user_id" is an array.<br>set "group_id" to -1 if you want to dissociate users from all groups',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.permissions.getList',
            '\Phyxo\Functions\Ws\Permission::getList',
            [
                'cat_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
                'group_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
                'user_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
            ],
            'Returns permissions: user ids and group ids having access to each album ; this list can be filtered.
    <br>Provide only one parameter!',
            ['admin_only' => true]
        );

        $this->service->addMethod(
            'pwg.permissions.add',
            '\Phyxo\Functions\Ws\Permission::add',
            [
                'cat_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY, 'type' => Server::WS_TYPE_ID],
                'group_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
                'user_id' => ['flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL, 'type' => Server::WS_TYPE_ID],
                'recursive' => ['default' => false, 'type' => Server::WS_TYPE_BOOL],
                'pwg_token' => [],
            ],
            'Adds permissions to an album.',
            ['admin_only' => true, 'post_only' => true]
        );

        $this->service->addMethod(
            'pwg.permissions.remove',
            '\Phyxo\Functions\Ws\Permission::remove',
            [
                'cat_id' => [
                    'flags' => Server::WS_PARAM_FORCE_ARRAY,
                    'type' => Server::WS_TYPE_ID
                ],
                'group_id' => [
                    'flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL,
                    'type' => Server::WS_TYPE_ID
                ],
                'user_id' => [
                    'flags' => Server::WS_PARAM_FORCE_ARRAY | Server::WS_PARAM_OPTIONAL,
                    'type' => Server::WS_TYPE_ID
                ],
                'pwg_token' => [],
            ],
            'Removes permissions from an album.',
            ['admin_only' => true, 'post_only' => true]
        );
    }
}
