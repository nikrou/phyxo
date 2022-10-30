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

namespace Themes\treflez;

use Phyxo\Conf;

class Config
{
    const CONF_PARAM = 'treflez';

    const TYPE_BOOL = 'bool';
    const TYPE_STRING = 'string';
    const TYPE_NUM = 'numeric';
    const TYPE_FILE = 'file';

    const KEY_FLUID_WIDTH = 'fluid_width';
    const KEY_FLUID_WIDTH_COL_XXL = 'fluid_width_col_xxl';
    const KEY_NAVBAR_MAIN_STYLE = 'navbar_main_style';
    const KEY_NAVBAR_MAIN_BG = 'navbar_main_bg';
    const KEY_NAVBAR_CONTEXTUAL_STYLE = 'navbar_contextual_style';
    const KEY_NAVBAR_CONTEXTUAL_BG = 'navbar_contextual_bg';

    const KEY_SLICK_ENABLED = 'slick_enabled';
    const KEY_SLICK_LAZYLOAD = 'slick_lazyload';
    const KEY_SLICK_INFINITE = 'slick_infinite';
    const KEY_SLICK_CENTERED = 'slick_centered';
    const KEY_PAGE_HEADER = 'page_header';
    const KEY_PAGE_HEADER_FULL = 'page_header_full';
    const KEY_PAGE_HEADER_IMAGE = 'page_header_image';
    const KEY_PAGE_HEADER_BOTH_NAVS = 'page_header_both_navs';
    const KEY_PICTURE_INFO = 'picture_info';
    const KEY_PHOTOSWIPE = 'photoswipe';
    const KEY_PHOTOSWIPE_LOOP = 'loop';
    const KEY_PHOTOSWIPE_METADATA = 'photoswipe_metadata';
    const KEY_THUMBNAIL_LINKTO = 'thumbnail_linkto';
    const KEY_THUMBNAIL_CAPTION = 'thumbnail_caption';
    const KEY_THUMBNAIL_CAT_DESC = 'thumbnail_cat_desc';
    const KEY_CATEGORY_WELLS = 'category_wells';
    const KEY_LOGO_IMAGE_ENABLED = 'logo_image_enabled';
    const KEY_LOGO_IMAGE_PATH = 'logo_image_path';
    const KEY_QUICKSEARCH_NAVBAR = 'quicksearch_navbar';
    const KEY_CAT_DESCRIPTIONS = 'cat_descriptions';
    const KEY_CAT_NB_IMAGES = 'cat_nb_images';

    const KEY_SOCIAL_ENABLED = 'social_enabled';
    const KEY_SOCIAL_BUTTONS = 'social_buttons';
    const KEY_SOCIAL_TWITTER = 'social_twitter';
    const KEY_SOCIAL_FACEBOOK = 'social_facebook';
    const KEY_SOCIAL_GOOGLE_PLUS = 'social_google_plus';
    const KEY_SOCIAL_PINTEREST = 'social_pinterest';
    const KEY_SOCIAL_VK = 'social_vk';

    const KEY_COMMENTS_TYPE = 'comments_type';
    const KEY_TAG_CLOUD_TYPE = 'tag_cloud_type';

    private $defaults = [
        self::KEY_FLUID_WIDTH => false,
        self::KEY_FLUID_WIDTH_COL_XXL => true,
        self::KEY_NAVBAR_MAIN_STYLE => 'navbar-light',
        self::KEY_NAVBAR_MAIN_BG => 'bg-light',
        self::KEY_NAVBAR_CONTEXTUAL_STYLE => 'navbar-light',
        self::KEY_NAVBAR_CONTEXTUAL_BG => 'bg-light',
        self::KEY_SLICK_ENABLED => true,
        self::KEY_SLICK_LAZYLOAD => 'ondemand',
        self::KEY_SLICK_INFINITE => false,
        self::KEY_SLICK_CENTERED => false,
        self::KEY_PAGE_HEADER => 'jumbotron',
        self::KEY_PAGE_HEADER_FULL => false,
        self::KEY_PAGE_HEADER_IMAGE => '',
        self::KEY_PAGE_HEADER_BOTH_NAVS => true,
        self::KEY_PICTURE_INFO => 'cards',
        self::KEY_PHOTOSWIPE => true,
        self::KEY_PHOTOSWIPE_LOOP => true,
        self::KEY_PHOTOSWIPE_METADATA => false,
        self::KEY_THUMBNAIL_LINKTO => 'picture',
        self::KEY_THUMBNAIL_CAPTION => true,
        self::KEY_THUMBNAIL_CAT_DESC => 'simple',
        self::KEY_CATEGORY_WELLS => 'never',
        self::KEY_LOGO_IMAGE_ENABLED => false,
        self::KEY_LOGO_IMAGE_PATH => '',
        self::KEY_QUICKSEARCH_NAVBAR => false,
        self::KEY_CAT_DESCRIPTIONS => false,
        self::KEY_CAT_NB_IMAGES => true,
        self::KEY_SOCIAL_ENABLED => true,
        self::KEY_SOCIAL_BUTTONS => false,
        self::KEY_SOCIAL_TWITTER => true,
        self::KEY_SOCIAL_FACEBOOK => true,
        self::KEY_SOCIAL_GOOGLE_PLUS => true,
        self::KEY_SOCIAL_PINTEREST => true,
        self::KEY_SOCIAL_VK => true,
        self::KEY_COMMENTS_TYPE => 'phyxo',
        self::KEY_TAG_CLOUD_TYPE => 'basic',
    ];

    private $types = [
        self::KEY_FLUID_WIDTH => self::TYPE_BOOL,
        self::KEY_FLUID_WIDTH_COL_XXL => self::TYPE_BOOL,
        self::KEY_NAVBAR_MAIN_STYLE => self::TYPE_STRING,
        self::KEY_NAVBAR_MAIN_BG => self::TYPE_STRING,
        self::KEY_NAVBAR_CONTEXTUAL_STYLE => self::TYPE_STRING,
        self::KEY_NAVBAR_CONTEXTUAL_BG => self::TYPE_STRING,
        self::KEY_SLICK_ENABLED => self::TYPE_BOOL,
        self::KEY_SLICK_LAZYLOAD => self::TYPE_STRING,
        self::KEY_SLICK_INFINITE => self::TYPE_BOOL,
        self::KEY_SLICK_CENTERED => self::TYPE_BOOL,
        self::KEY_PAGE_HEADER => self::TYPE_STRING,
        self::KEY_PAGE_HEADER_FULL => self::TYPE_BOOL,
        self::KEY_PAGE_HEADER_IMAGE => self::TYPE_STRING,
        self::KEY_PAGE_HEADER_BOTH_NAVS => self::TYPE_BOOL,
        self::KEY_PICTURE_INFO => self::TYPE_STRING,
        self::KEY_PHOTOSWIPE => self::TYPE_BOOL,
        self::KEY_PHOTOSWIPE_LOOP => self::TYPE_BOOL,
        self::KEY_PHOTOSWIPE_METADATA => self::TYPE_BOOL,
        self::KEY_THUMBNAIL_LINKTO => self::TYPE_STRING,
        self::KEY_THUMBNAIL_CAPTION => self::TYPE_BOOL,
        self::KEY_THUMBNAIL_CAT_DESC => self::TYPE_STRING,
        self::KEY_CATEGORY_WELLS => self::TYPE_STRING,
        self::KEY_LOGO_IMAGE_ENABLED => self::TYPE_BOOL,
        self::KEY_LOGO_IMAGE_PATH => self::TYPE_STRING,
        self::KEY_QUICKSEARCH_NAVBAR => self::TYPE_BOOL,
        self::KEY_CAT_DESCRIPTIONS => self::TYPE_BOOL,
        self::KEY_CAT_NB_IMAGES => self::TYPE_BOOL,
        self::KEY_SOCIAL_ENABLED => self::TYPE_BOOL,
        self::KEY_SOCIAL_BUTTONS => self::TYPE_BOOL,
        self::KEY_SOCIAL_TWITTER => self::TYPE_BOOL,
        self::KEY_SOCIAL_FACEBOOK => self::TYPE_BOOL,
        self::KEY_SOCIAL_GOOGLE_PLUS => self::TYPE_BOOL,
        self::KEY_SOCIAL_PINTEREST => self::TYPE_BOOL,
        self::KEY_SOCIAL_VK => self::TYPE_BOOL,
        self::KEY_COMMENTS_TYPE => self::TYPE_STRING,
        self::KEY_TAG_CLOUD_TYPE => self::TYPE_STRING,
    ];

    private $config = [];
    private $core_config = [];

    public function __construct(Conf $conf)
    {
        $this->core_config = $conf;

        // Create initial config if necessary
        if (!isset($conf[self::CONF_PARAM])) {
            $this->createDefaultConfig();
        }

        $this->config = $conf[self::CONF_PARAM];

        $this->populateConfig();
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function __set(string $key, $value): void
    {
        if (array_key_exists($key, $this->defaults)) {
            switch ($this->types[$key]) {
                case self::TYPE_STRING:
                    $this->config[$key] = !empty($value) ? $value : null;
                    break;
                case self::TYPE_BOOL:
                    $this->config[$key] = $value ? true : false;
                    break;
                case self::TYPE_NUM:
                    $this->config[$key] = is_numeric($value) ? $value : $this->defaults[$key];
                    break;
            }
        }
    }

    public function __get(string $key)
    {
        if (array_key_exists($key, $this->defaults)) {
            switch ($this->types[$key]) {
                case self::TYPE_STRING:
                case self::TYPE_BOOL:
                case self::TYPE_NUM:
                    return $this->config[$key];
            }
        } else {
            return null;
        }
    }

    public function fromPost(array $post): void
    {
        foreach (array_keys($this->defaults) as $key) {
            $this->__set($key, isset($post[$key]) ? $post[$key] : null);
        }
    }

    public function save(): void
    {
        $this->core_config->addOrUpdateParam(self::CONF_PARAM, $this->config, 'json');
    }

    private function createDefaultConfig(): void
    {
        $this->config = $this->defaults;
    }

    private function populateConfig(): void
    {
        foreach ($this->defaults as $key => $value) {
            if (!isset($this->config[$key])) {
                $this->config[$key] = $value;
            }
        }
    }
}
