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

namespace Phyxo\Image;

/**
 * A source image is used to get a derivative image. It is either
 * the original file for a jpg/png/... or a 'representative' image
 * of a  non image file or a standard icon for the non-image file.
 */
class SrcImage
{
    const IS_ORIGINAL = 0x01;
    const IS_MIMETYPE = 0x02;
    const DIM_NOT_GIVEN = 0x04;

    /** @var int */
    public $id;
    /** @var string */
    public $rel_path;
    /** @var int */
    public $rotation = 0;
    /** @var int[] */
    private $size = null;
    /** @var int */
    private $flags = 0;

    /**
     * @param array $infos assoc array of data from images table
     */
    public function __construct($infos)
    {
        global $conf;

        $this->id = $infos['id'];
        $ext = get_extension($infos['path']);
        if (in_array($ext, $conf['picture_ext'])) {
            $this->rel_path = $infos['path'];
            $this->flags |= self::IS_ORIGINAL;
        } elseif (!empty($infos['representative_ext'])) {
            $this->rel_path = original_to_representative($infos['path'], $infos['representative_ext']);
        } else {
            $ext = strtolower($ext);
            $this->rel_path = trigger_change('get_mimetype_location', get_themeconf('mime_icon_dir') . $ext . '.png', $ext);
            $this->flags |= self::IS_MIMETYPE;
            if (($size = @getimagesize(PHPWG_ROOT_PATH . $this->rel_path)) === false) {
                $this->rel_path = 'themes/default/icon/mimetypes/unknown.png';
                $size = getimagesize(PHPWG_ROOT_PATH . $this->rel_path);
            }
            $this->size = array($size[0], $size[1]);
        }

        if (!$this->size) {
            if (isset($infos['width']) && isset($infos['height'])) {
                $width = $infos['width'];
                $height = $infos['height'];

                $this->rotation = intval($infos['rotation']) % 4;
                // 1 or 5 =>  90 clockwise
                // 3 or 7 => 270 clockwise
                if ($this->rotation % 2) {
                    $width = $infos['height'];
                    $height = $infos['width'];
                }

                $this->size = array($width, $height);
            } elseif (!array_key_exists('width', $infos)) {
                $this->flags |= self::DIM_NOT_GIVEN;
            }
        }
    }

    /**
     * @return bool
     */
    public function is_original()
    {
        return $this->flags & self::IS_ORIGINAL;
    }

    /**
     * @return bool
     */
    public function is_mimetype()
    {
        return $this->flags & self::IS_MIMETYPE;
    }

    /**
     * @return string
     */
    public function get_path()
    {
        return PHPWG_ROOT_PATH . $this->rel_path;
    }

    /**
     * @return string
     */
    public function get_url()
    {
        $url = \Phyxo\Functions\URL::get_root_url() . $this->rel_path;
        if (!($this->flags & self::IS_MIMETYPE)) {
            $url = trigger_change('get_src_image_url', $url, $this);
        }
        return \Phyxo\Functions\URL::embellish_url($url);
    }

    /**
     * @return bool
     */
    public function has_size()
    {
        return $this->size != null;
    }

    /**
     * @return int[]|null 0=width, 1=height or null if fail to compute size
     */
    public function get_size()
    {
        global $conn;

        if ($this->size == null) {
            if ($this->flags & self::DIM_NOT_GIVEN)
                fatal_error('SrcImage dimensions required but not provided');
            // probably not metadata synced
            if (is_readable($this->get_path()) && ($size = getimagesize($this->get_path())) !== false) {
                $this->size = array($size[0], $size[1]);
                $conn->db_query('UPDATE ' . IMAGES_TABLE . ' SET width=' . $size[0] . ', height=' . $size[1] . ' WHERE id=' . $this->id);
            }
        }
        return $this->size;
    }
}
