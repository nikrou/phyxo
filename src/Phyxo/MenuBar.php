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

namespace Phyxo;

use Phyxo\Conf;
use Phyxo\Block\BlockManager;
use Symfony\Component\Routing\RouterInterface;
use App\DataMapper\CategoryMapper;
use App\DataMapper\UserMapper;
use App\DataMapper\TagMapper;
use Phyxo\Functions\Tag;
use Phyxo\Functions\URL;

class MenuBar
{
    private $conf, $menu, $router, $categoryMapper, $userMapper, $tagMapper;
    private $route = null, $items = [], $tags = [];

    public function __construct(Conf $conf, RouterInterface $router, CategoryMapper $categoryMapper, UserMapper $userMapper, TagMapper $tagMapper)
    {
        $this->conf = $conf;
        $this->router = $router;
        $this->categoryMapper = $categoryMapper;
        $this->userMapper = $userMapper;
        $this->tagMapper = $tagMapper;
    }

    public function setRoute(string $route)
    {
        $this->route = $route;
    }

    public function  setCurrentImages(array $items = [])
    {
        $this->items = $items;
    }

    public function  setCurrentTags(array $tags = [])
    {
        $this->tags = $tags;
    }

    public function getBlocks()
    {
        $tpl_params = [];

        $this->menu = new BlockManager('menubar');
        $this->menu->loadDefaultBlocks();
        $this->menu->loadRegisteredBlocks();
        $this->menu->loadMenuConfig(json_decode($this->conf['blk_menubar'], true));
        $this->menu->prepareDisplay();

        $this->linksBlock();
        $this->categoriesBlock();
        $this->tagsBlock();
        $this->specialsBlock();
        $this->menuBlock();
        $tpl_params = array_merge($tpl_params, $this->identificationBlock());
        $tpl_params['blocks'] = $this->menu->getDisplayBlocks();


        return $tpl_params;
    }

    protected function linksBlock()
    {
        if (($block = $this->menu->getBlock('mbLinks')) && !empty($this->conf['links'])) {
            $block->data = [];
            foreach ($this->conf['links'] as $url => $url_data) {
                if (!is_array($url_data)) {
                    $url_data = ['label' => $url_data];
                }

                $tpl_var = [
                    'URL' => $url,
                    'LABEL' => $url_data['label']
                ];

                if (!isset($url_data['new_window']) || $url_data['new_window']) {
                    $tpl_var['new_window'] = [
                        'NAME' => (isset($url_data['nw_name']) ? $url_data['nw_name'] : ''),
                        'FEATURES' => (isset($url_data['nw_features']) ? $url_data['nw_features'] : '')
                    ];
                }
                $block->data[] = $tpl_var;
            }

            if (!empty($block->data)) {
                $block->template = 'menubar_links.tpl';
            }
        }
    }

    protected function categoriesBlock()
    {
        if (($block = $this->menu->getBlock('mbCategories')) != null) {
            $block->data = [
                'NB_PICTURE' => $this->userMapper->getUser()->getNbTotalImages(),
                'MENU_CATEGORIES' => $this->categoryMapper->getRecursiveCategoriesMenu($this->userMapper->getUser(), []),
                'U_CATEGORIES' => $this->router->generate('albums_flat'),
            ];
            $block->template = 'menubar_categories.tpl';
        }
    }

    protected function tagsBlock()
    {
        if (($block = $this->menu->getBlock('mbTags')) != null) {
            if ($this->route === 'images_by_tags') {
                $tags = $this->tagMapper->getCommonTags(
                    $this->userMapper->getUser(),
                    $this->items,
                    $this->conf['menubar_tag_cloud_items_number'],
                    array_map(function($tag) {
                        return $tag['id'];
                    }, $this->tags)
                );

                $tags = Tag::addLevelToTags($tags);
                foreach ($tags as $tag) {
                    $block->data[] = array_merge(
                        $tag,
                        [
                            'U_ADD' => $this->router->generate(
                                'images_by_tags',
                                ['tag_ids' => implode('/', array_map('\Phyxo\Functions\URL::tagToUrl', array_merge($this->tags, [$tag])))]
                            ),
                            'URL' => $this->router->generate(
                                'images_by_tags',
                                ['tag_ids' => URL::tagToUrl($tag)]
                            )
                        ]
                    );
                }
            } elseif ($this->route === 'tags') {
                $tags = $this->tagMapper->getAvailableTags($this->userMapper->getUser());
                foreach ($tags as $tag) {
                    $block->data[] = array_merge(
                        $tag, ['URL' => $this->router->generate('images_by_tags', ['tag_ids' => URL::tagToUrl($tag)])                        ]
                    );
                }
            } else {
                $tags = Tag::addLevelToTags(
                    $this->tagMapper->getCommonTags($this->userMapper->getUser(), $this->items, $this->conf['content_tag_cloud_items_number'])
                );
                foreach ($tags as $tag) {
                    $block->data[] = array_merge(
                        $tag, ['URL' => $this->router->generate('images_by_tags', ['tag_ids' => URL::tagToUrl($tag)])                        ]
                    );
                }
            }

            if (!empty($block->data)) {
                $block->template = 'menubar_tags.tpl';
            }
        }
    }

    protected function specialsBlock()
    {
        if (($block = $this->menu->getBlock('mbSpecials')) != null) {
            if (!$this->userMapper->getUser()->isGuest()) {
                $block->data['favorites'] = [
                    'URL' => $this->router->generate('favorites'),
                    'TITLE' => \Phyxo\Functions\Language::l10n('display your favorites photos'),
                    'NAME' => \Phyxo\Functions\Language::l10n('Your favorites')
                ];
            }

            $block->data['most_visited'] = [
                'URL' => $this->router->generate('most_visited'),
                'TITLE' => \Phyxo\Functions\Language::l10n('display most visited photos'),
                'NAME' => \Phyxo\Functions\Language::l10n('Most visited')
            ];

            if ($this->conf['rate']) {
                $block->data['best_rated'] = [
                    'URL' => $this->router->generate('best_rated'),
                    'TITLE' => \Phyxo\Functions\Language::l10n('display best rated photos'),
                    'NAME' => \Phyxo\Functions\Language::l10n('Best rated')
                ];
            }

            $block->data['recent_pics'] = [
                'URL' => $this->router->generate('recent_pics'),
                'TITLE' => \Phyxo\Functions\Language::l10n('display most recent photos'),
                'NAME' => \Phyxo\Functions\Language::l10n('Recent photos'),
            ];

            $block->data['recent_cats'] = [
                'URL' => $this->router->generate('recent_cats'),
                'TITLE' => \Phyxo\Functions\Language::l10n('display recently updated albums'),
                'NAME' => \Phyxo\Functions\Language::l10n('Recent albums'),
            ];

            $block->data['random'] = [
                'URL' => $this->router->generate('random'),
                'TITLE' => \Phyxo\Functions\Language::l10n('display a set of random photos'),
                'NAME' => \Phyxo\Functions\Language::l10n('Random photos'),
            ];

            $block->data['calendar'] = [
                'URL' => $this->router->generate(
                    'calendar_categories_monthly',
                    [
                        'date_type' => $this->conf['calendar_datefield'] === 'date_available' ? 'posted' : 'created',
                        'view_type' => 'calendar'
                    ]
                ),
                'TITLE' => \Phyxo\Functions\Language::l10n('display each day with photos, month per month'),
                'NAME' => \Phyxo\Functions\Language::l10n('Calendar'),
            ];
            $block->template = 'menubar_specials.tpl';
        }
    }

    protected function menuBlock()
    {
        if (($block = $this->menu->getBlock('mbMenu')) != null) {
            // quick search block will be displayed only if data['qsearch'] is set to "yes"
            $block->data['qsearch'] = true;

            $block->data['tags'] = [
                'TITLE' => \Phyxo\Functions\Language::l10n('display available tags'),
                'NAME' => \Phyxo\Functions\Language::l10n('Tags'),
                'URL' => $this->router->generate('tags'),
                'COUNTER' => $this->userMapper->getNumberAvailableTags($this->userMapper->getUser(), []),
            ];

            $block->data['search'] = [
                'TITLE' => \Phyxo\Functions\Language::l10n('search'),
                'NAME' => \Phyxo\Functions\Language::l10n('Search'),
                'URL' => $this->router->generate('search'),
                'REL' => 'rel="search"'
            ];

            if ($this->conf['activate_comments']) {
                $block->data['comments'] = [
                    'TITLE' => \Phyxo\Functions\Language::l10n('display last user comments'),
                    'NAME' => \Phyxo\Functions\Language::l10n('Comments'),
                    'URL' => $this->router->generate('comments'),
                    'COUNTER' => $this->userMapper->getNumberAvailableComments(),
                ];
            }

            $block->data['about'] = [
                'TITLE' => \Phyxo\Functions\Language::l10n('About Phyxo'),
                'NAME' => \Phyxo\Functions\Language::l10n('About'),
                'URL' => $this->router->generate('about'),
            ];

            $block->data['rss'] = [
                'TITLE' => \Phyxo\Functions\Language::l10n('RSS feed'),
                'NAME' => \Phyxo\Functions\Language::l10n('Notification'),
                'URL' => $this->router->generate('notification'),
            ];
            $block->template = 'menubar_menu.tpl';
        }
    }

    protected function identificationBlock(): array
    {
        $tpl_params = [];

        if ($this->userMapper->getUser()->isGuest()) {
            $tpl_params['U_LOST_PASSWORD'] = $this->router->generate('forgot_password');
            $tpl_params['AUTHORIZE_REMEMBERING'] = $this->conf['authorize_remembering'];

            if ($this->conf['allow_user_registration']) {
                $tpl_params['U_REGISTER'] = $this->router->generate('register');
            }
        } else {
            $tpl_params['APP_USER'] = $this->userMapper->getUser();
            $tpl_params['USERNAME'] = $this->userMapper->getUser()->getUsername();
            $tpl_params['U_PROFILE'] = $this->router->generate('profile');
            $tpl_params['U_LOGOUT'] = $this->router->generate('logout');

            if ($this->userMapper->isAdmin()) {
                $tpl_params['U_ADMIN'] = $this->router->generate('admin_home');
            }
        }

        if (($block = $this->menu->getBlock('mbIdentification')) != null) {
            $block->template = 'menubar_identification.tpl';
        }

        return $tpl_params;
    }
}
