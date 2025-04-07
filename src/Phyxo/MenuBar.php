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

use App\DataMapper\AlbumMapper;
use App\DataMapper\TagMapper;
use App\DataMapper\UserMapper;
use App\Entity\Tag;
use App\Security\AppUserService;
use Phyxo\Block\BlockManager;
use Phyxo\Block\DisplayBlock;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuBar
{
    private BlockManager $menu;
    private string $route = '';

    /** @var array<int> */
    private array $items = [];

    /** @var Tag[] */
    private array $tags = [];

    public function __construct(
        private Conf $conf,
        private readonly RouterInterface $router,
        private readonly AlbumMapper $albumMapper,
        private readonly AppUserService $appUserService,
        private readonly UserMapper $userMapper,
        private readonly TagMapper $tagMapper,
        private readonly TranslatorInterface $translator,
        private readonly string $defaultDateType,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @param array<int> $items
     */
    public function setCurrentImages(array $items = []): void
    {
        $this->items = $items;
    }

    /**
     * @param Tag[] $tags
     */
    public function setCurrentTags(array $tags = []): void
    {
        $this->tags = $tags;
    }

    /**
     * @return DisplayBlock[]
     */
    public function getBlocks(): array
    {
        $this->menu = new BlockManager('menubar');
        $this->menu->loadDefaultBlocks();
        $this->menu->loadRegisteredBlocks($this->eventDispatcher);
        if (empty($this->conf['blk_menubar'])) {
            $this->menu->loadDefaultBlocks();
        } else {
            $this->menu->loadMenuConfig($this->conf['blk_menubar']);
        }

        $this->menu->prepareDisplay();

        $this->linksBlock();
        $this->categoriesBlock();
        $this->tagsBlock();
        $this->specialsBlock();
        $this->menuBlock();
        $this->identificationBlock();

        return $this->menu->getDisplayBlocks();
    }

    protected function linksBlock(): void
    {
        if (($block = $this->menu->getBlock('mbLinks')) && !empty($this->conf['links'])) {
            $data = [];
            foreach ($this->conf['links'] as $url => $url_data) {
                if (!is_array($url_data)) {
                    $url_data = ['label' => $url_data];
                }

                $tpl_var = [
                    'URL' => $url,
                    'LABEL' => $url_data['label'],
                ];

                if (!isset($url_data['new_window']) || $url_data['new_window']) {
                    $tpl_var['new_window'] = [
                        'NAME' => ($url_data['nw_name'] ?? ''),
                        'FEATURES' => ($url_data['nw_features'] ?? ''),
                    ];
                }

                $data[] = $tpl_var;
            }

            $block->setData($data);

            if ($block->getData() !== []) {
                $block->setTemplate('menubar_links');
            }
        }
    }

    protected function categoriesBlock(): void
    {
        if (($block = $this->menu->getBlock('mbCategories')) != null) {
            $block->setData([
                'NB_PICTURE' => $this->appUserService->getUser()->getUserInfos()->getNbTotalImages(),
                'MENU_CATEGORIES' => $this->albumMapper->getRecursiveAlbumsMenu($this->appUserService->getUser()),
            ]);
            $block->setTemplate('menubar_categories');
        }
    }

    protected function tagsBlock(): void
    {
        if (($block = $this->menu->getBlock('mbTags')) != null) {
            if ($this->getRoute() === 'images_by_tags') {
                $tags = $this->tagMapper->getCommonTags(
                    $this->appUserService->getUser(),
                    $this->items,
                    $this->conf['menubar_tag_cloud_items_number'],
                    array_map(fn ($tag): int => $tag->getId(), $this->tags)
                );

                $tags = $this->tagMapper->addLevelToTags($tags);
                $data = [];
                foreach ($tags as $tag) {
                    $data[] = array_merge(
                        $tag->toArray(),
                        [
                            'U_ADD' => $this->router->generate(
                                'images_by_tags',
                                ['tag_ids' => implode('/', array_map(fn (Tag $tag): string => $tag->toUrl(), array_merge($this->tags, [$tag])))]
                            ),
                            'URL' => $this->router->generate(
                                'images_by_tags',
                                ['tag_ids' => $tag->toUrl()]
                            ),
                        ]
                    );
                }

                $block->setData($data);
            } elseif ($this->getRoute() === 'tags') {
                $tags = $this->tagMapper->getAvailableTags($this->appUserService->getUser());
                $data = [];
                foreach ($tags as $tag) {
                    $data[] = array_merge(
                        $tag->toArray(),
                        ['URL' => $this->router->generate('images_by_tags', ['tag_ids' => $tag->toUrl()])]
                    );
                }

                $block->setData($data);
            } else {
                $tags = $this->tagMapper->addLevelToTags(
                    $this->tagMapper->getCommonTags($this->appUserService->getUser(), $this->items, $this->conf['content_tag_cloud_items_number'])
                );
                $data = [];
                foreach ($tags as $tag) {
                    $data[] = array_merge(
                        $tag->toArray(),
                        ['URL' => $this->router->generate('images_by_tags', ['tag_ids' => $tag->toUrl()])]
                    );
                }

                $block->setData($data);
            }

            if ($block->getData() !== []) {
                $block->setTemplate('menubar_tags');
            }
        }
    }

    protected function specialsBlock(): void
    {
        if (($block = $this->menu->getBlock('mbSpecials')) != null) {
            $data = [];
            if (!$this->appUserService->isGuest()) {
                $data['favorites'] = [
                    'URL' => $this->router->generate('favorites'),
                    'TITLE' => $this->translator->trans('display your favorites photos'),
                    'NAME' => $this->translator->trans('Your favorites'),
                ];
            }

            $data['most_visited'] = [
                'URL' => $this->router->generate('most_visited'),
                'TITLE' => $this->translator->trans('display most visited photos'),
                'NAME' => $this->translator->trans('Most visited'),
            ];

            if ($this->conf['rate']) {
                $data['best_rated'] = [
                    'URL' => $this->router->generate('best_rated'),
                    'TITLE' => $this->translator->trans('display best rated photos'),
                    'NAME' => $this->translator->trans('Best rated'),
                ];
            }

            $data['recent_pics'] = [
                'URL' => $this->router->generate('recent_pics'),
                'TITLE' => $this->translator->trans('display most recent photos'),
                'NAME' => $this->translator->trans('Recent photos'),
            ];

            $data['recent_albums'] = [
                'URL' => $this->router->generate('recent_albums'),
                'TITLE' => $this->translator->trans('display recently updated albums'),
                'NAME' => $this->translator->trans('Recent albums'),
            ];

            $data['random'] = [
                'URL' => $this->router->generate('random'),
                'TITLE' => $this->translator->trans('display a set of random photos'),
                'NAME' => $this->translator->trans('Random photos'),
            ];

            $data['calendar'] = [
                'URL' => $this->router->generate(
                    'calendar',
                    [
                        'date_type' => $this->defaultDateType,
                    ]
                ),
                'TITLE' => $this->translator->trans('display each day with photos, month per month'),
                'NAME' => $this->translator->trans('Calendar'),
            ];

            $block->setData($data);
            $block->setTemplate('menubar_specials');
        }
    }

    protected function menuBlock(): void
    {
        if (($block = $this->menu->getBlock('mbMenu')) != null) {
            $data = [];
            // quick search block will be displayed only if data['qsearch'] is set to "yes"
            $data['qsearch'] = true;

            $data['tags'] = [
                'TITLE' => $this->translator->trans('display available tags'),
                'NAME' => $this->translator->trans('Tags'),
                'URL' => $this->router->generate('tags'),
                'COUNTER' => $this->userMapper->getNumberAvailableTags(),
            ];

            $data['search'] = [
                'TITLE' => $this->translator->trans('search'),
                'NAME' => $this->translator->trans('Search'),
                'URL' => $this->router->generate('search'),
                'REL' => 'rel="search"',
            ];

            if ($this->conf['activate_comments']) {
                $data['comments'] = [
                    'TITLE' => $this->translator->trans('display last user comments'),
                    'NAME' => $this->translator->trans('Comments'),
                    'URL' => $this->router->generate('comments'),
                    'COUNTER' => $this->userMapper->getNumberAvailableComments(),
                ];
            }

            $data['about'] = [
                'TITLE' => $this->translator->trans('About Phyxo'),
                'NAME' => $this->translator->trans('About'),
                'URL' => $this->router->generate('about'),
            ];

            $data['rss'] = [
                'TITLE' => $this->translator->trans('RSS feed'),
                'NAME' => $this->translator->trans('Notification'),
                'URL' => $this->router->generate('notification'),
            ];

            $block->setData($data);
            $block->setTemplate('menubar_menu');
        }
    }

    protected function identificationBlock(): void
    {
        if (($block = $this->menu->getBlock('mbIdentification')) != null) {
            $data = [];
            if ($this->appUserService->isGuest()) {
                $data['U_LOST_PASSWORD'] = $this->router->generate('forgot_password');
                $data['AUTHORIZE_REMEMBERING'] = $this->conf['authorize_remembering'];

                if ($this->conf['allow_user_registration']) {
                    $data['U_REGISTER'] = $this->router->generate('register');
                }
            } else {
                $data['APP_USER'] = $this->appUserService->getUser();
                $data['USERNAME'] = $this->appUserService->getUser()->getUserIdentifier();
                $data['U_LOGOUT'] = $this->router->generate('logout');
            }

            $block->setData($data);
            $block->setTemplate('menubar_identification');
        }
    }
}
