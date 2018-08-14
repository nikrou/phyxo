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

interface ImageInterface
{
    public function get_width();

    public function get_height();

    public function set_compression_quality($quality);

    public function crop($width, $height, $x, $y);

    public function strip();

    public function rotate($rotation);

    public function resize($width, $height);

    public function sharpen($amount);

    public function compose($overlay, $x, $y, $opacity);

    public function write($destination_filepath);
}