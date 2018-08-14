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

use Phyxo\Image\ImageInterface;

class ImageGD implements ImageInterface
{
    private $image, $quality = 95;

    public function __construct($source_filepath)
    {
        $gd_info = gd_info();
        $extension = strtolower(\Phyxo\Functions\Utils::get_extension($source_filepath));

        if (in_array($extension, array('jpg', 'jpeg'))) {
            $this->image = imagecreatefromjpeg($source_filepath);
        } elseif ($extension == 'png') {
            $this->image = imagecreatefrompng($source_filepath);
        } elseif ($extension == 'gif' and $gd_info['GIF Read Support'] and $gd_info['GIF Create Support']) {
            $this->image = imagecreatefromgif($source_filepath);
        } else {
            die('[Image GD] unsupported file extension');
        }
    }

    public function get_width()
    {
        return imagesx($this->image);
    }

    public function get_height()
    {
        return imagesy($this->image);
    }

    public function crop($width, $height, $x, $y)
    {
        $dest = imagecreatetruecolor($width, $height);

        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        if (function_exists('imageantialias')) {
            imageantialias($dest, true);
        }

        $result = imagecopymerge($dest, $this->image, 0, 0, $x, $y, $width, $height, 100);

        if ($result !== false) {
            imagedestroy($this->image);
            $this->image = $dest;
        } else {
            imagedestroy($dest);
        }

        return $result;
    }

    public function strip()
    {
        return true;
    }

    public function rotate($rotation)
    {
        $dest = imagerotate($this->image, $rotation, 0);
        imagedestroy($this->image);
        $this->image = $dest;
    }

    public function set_compression_quality($quality)
    {
        $this->quality = $quality;
    }

    public function resize($width, $height)
    {
        $dest = imagecreatetruecolor($width, $height);

        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        if (function_exists('imageantialias')) {
            imageantialias($dest, true);
        }

        $result = imagecopyresampled($dest, $this->image, 0, 0, 0, 0, $width, $height, $this->get_width(), $this->get_height());

        if ($result !== false) {
            imagedestroy($this->image);
            $this->image = $dest;
        } else {
            imagedestroy($dest);
        }

        return $result;
    }

    public function sharpen($amount)
    {
        $m = \Phyxo\Image\Image::get_sharpen_matrix($amount);
        return imageconvolution($this->image, $m, 1, 0);
    }

    public function compose($overlay, $x, $y, $opacity)
    {
        $ioverlay = $overlay->image->image;
        /* A replacement for php's imagecopymerge() function that supports the alpha channel
           See php bug #23815:  http://bugs.php.net/bug.php?id=23815 */

        $ow = imagesx($ioverlay);
        $oh = imagesy($ioverlay);

		// Create a new blank image the site of our source image
        $cut = imagecreatetruecolor($ow, $oh);

		// Copy the blank image into the destination image where the source goes
        imagecopy($cut, $this->image, 0, 0, $x, $y, $ow, $oh);

		// Place the source image in the destination image
        imagecopy($cut, $ioverlay, 0, 0, 0, 0, $ow, $oh);
        imagecopymerge($this->image, $cut, $x, $y, 0, 0, $ow, $oh, $opacity);
        imagedestroy($cut);
    }

    public function write($destination_filepath)
    {
        $extension = strtolower(\Phyxo\Functions\Utils::get_extension($destination_filepath));

        if ($extension == 'png') {
            imagepng($this->image, $destination_filepath);
        } elseif ($extension == 'gif') {
            imagegif($this->image, $destination_filepath);
        } else {
            imagejpeg($this->image, $destination_filepath, $this->quality);
        }
    }

    public function destroy()
    {
        imagedestroy($this->image);
    }
}
