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

use Phyxo\Functions\Utils;

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
    public function __construct(array $infos, array $picture_ext)
    {
        $this->id = $infos['id'];
        $ext = Utils::get_extension($infos['path']);
        if (in_array($ext, $picture_ext)) {
            $this->rel_path = $infos['path'];
            $this->flags = self::IS_ORIGINAL;
        } elseif (!empty($infos['representative_ext'])) {
            $this->rel_path = \Phyxo\Functions\Utils::original_to_representative($infos['path'], $infos['representative_ext']);
        } else {
            $ext = strtolower($ext);
            $this->rel_path = 'themes/treflez/icon' . $ext . '.png';
            $this->flags = self::IS_MIMETYPE;
            if (($size = @getimagesize(__DIR__ . '/../../../' . $this->rel_path)) === false) {
                $this->rel_path = 'themes/treflez/icon/mimetypes/unknown.png';
                $size = getimagesize(__DIR__ . '/../../../' . $this->rel_path);
            }
            $this->size = [$size[0], $size[1]];
        }

        if (!$this->size) {
            if (isset($infos['width'], $infos['height'])) {
                $width = $infos['width'];
                $height = $infos['height'];

                $this->rotation = intval($infos['rotation']) % 4;
                // 1 or 5 =>  90 clockwise
                // 3 or 7 => 270 clockwise
                if ($this->rotation % 2) {
                    $width = $infos['height'];
                    $height = $infos['width'];
                }

                $this->size = [$width, $height];
            } elseif (!array_key_exists('width', $infos)) {
                $this->flags = self::DIM_NOT_GIVEN;
            }
        }
    }

    public function is_original(): bool
    {
        return $this->flags === self::IS_ORIGINAL;
    }

    public function is_mimetype(): bool
    {
        return $this->flags === self::IS_MIMETYPE;
    }

    public function get_path(): string
    {
        return __DIR__ . '/../../../' . $this->rel_path;
    }

    public function getUrl(): string
    {
        $url = \Phyxo\Functions\URL::get_root_url() . $this->rel_path;

        return \Phyxo\Functions\URL::embellish_url($url);
    }

    public function has_size(): bool
    {
        return $this->size !== null;
    }

    /**
     * @return int[]|null 0=width, 1=height or null if fail to compute size
     */
    public function get_size()
    {
        if ($this->size == null) {
            if ($this->flags === self::DIM_NOT_GIVEN) {
                throw new \Exception('SrcImage dimensions required but not provided');
            }
        }

        return $this->size;
    }
}
